package com.featurestic.sdk;

import android.content.ContentResolver;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.database.Cursor;
import android.net.Uri;
import android.provider.OpenableColumns;
import android.util.Log;

import com.featurestic.box.core.env.BEnvironment;

import java.io.File;
import java.io.FileInputStream;
import java.io.FileOutputStream;
import java.io.IOException;
import java.io.InputStream;
import java.io.OutputStream;
import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;
import java.util.ArrayList;
import java.util.Collections;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Locale;
import java.util.Map;
import java.util.Properties;
import java.util.Set;
import java.util.TreeSet;

/**
 * Verified expansion-file staging for the virtual game filesystem.
 *
 * Android 11+ does not grant third-party access to another application's
 * Android/obb folder. We try accessible host files first, then previously
 * user-selected ACTION_OPEN_DOCUMENT URIs (such as copies in Downloads).
 * This does not bypass Android's storage access rules.
 */
public final class ObbSyncManager {
    private static final String TAG = "FeaturesticObbSync";
    private static final String PREFS = "featurestic_obb_documents_v2";
    private static final String VERSION = "2";
    private static final int BUFFER_SIZE = 1024 * 1024;
    private static final int MAX_DOCUMENTS = 16;
    private static final String NEED_SOURCE =
            "OBB_SOURCE_REQUIRED: Android could not read the game's OBB. "
            + "Select your own matching .obb file(s) from Downloads or Documents.";

    private ObbSyncManager() {}

    public interface Progress {
        void update(int percent, String title, String detail);
    }

    private static void assertPackage(String pkg) throws IOException {
        if (pkg == null || !pkg.matches("[A-Za-z0-9_]+(\\.[A-Za-z0-9_]+)+")) {
            throw new IOException("Invalid game package");
        }
    }

    /**
     * Android expansion APK standard: main.VERSION.PACKAGE.obb / patch.VERSION.PACKAGE.obb.
     * Prevents accidentally importing an unrelated game's OBB.
     */
    private static boolean validName(String name, String pkg) {
        if (name == null || pkg == null || name.contains("/") || name.contains("\\")
                || name.contains("..") || name.length() > 220) {
            return false;
        }
        String suffix = "." + pkg + ".obb";
        if (!name.endsWith(suffix)) return false;
        String prefix = name.substring(0, name.length() - suffix.length());
        return prefix.matches("(main|patch)\\.[0-9]+");
    }

    private static File manifestFile(Context context, String pkg) {
        return new File(context.getDir("verified_obb", Context.MODE_PRIVATE), pkg + ".properties");
    }

    private static File destination(String pkg, String filename) {
        return new File(BEnvironment.getExternalObbDir(pkg), filename);
    }

    private static Properties readManifest(Context context, String pkg) {
        Properties entries = new Properties();
        File source = manifestFile(context, pkg);
        if (!source.isFile()) return entries;
        try (InputStream input = new FileInputStream(source)) {
            entries.load(input);
            if (!VERSION.equals(entries.getProperty("version"))) entries.clear();
        } catch (Exception e) {
            entries.clear();
        }
        return entries;
    }

    private static void writeManifest(Context context, String pkg, Properties entries) throws IOException {
        entries.setProperty("version", VERSION);
        File file = manifestFile(context, pkg);
        File tmp = new File(file.getParentFile(), file.getName() + ".new");
        try {
            try (FileOutputStream out = new FileOutputStream(tmp)) {
                entries.store(out, "Verified virtual OBB inventory");
                out.getFD().sync();
            }
            if (!tmp.renameTo(file)) {
                throw new IOException("Unable to save verified OBB manifest");
            }
        } finally {
            if (tmp.exists()) tmp.delete();
        }
    }

    private static String[] parseEntry(String encoded) {
        if (encoded == null) return null;
        String[] parts = encoded.split(":", 2);
        if (parts.length != 2 || !parts[1].matches("[a-f0-9]{64}")) return null;
        try {
            if (Long.parseLong(parts[0]) <= 0) return null;
            return parts;
        } catch (NumberFormatException e) {
            return null;
        }
    }

    /**
     * Light check for the UI: a saved manifest plus all expected non-empty files
     * with the recorded sizes. SHA-256 is always re-checked on the worker thread
     * immediately before launching the virtual game.
     */
    public static boolean isReadyQuick(Context context, String pkg) {
        if (context == null) return false;
        try {
            assertPackage(pkg);
            Properties entries = readManifest(context, pkg);
            int count = 0;
            for (String key : entries.stringPropertyNames()) {
                if (!key.startsWith("obb.")) continue;
                String name = key.substring(4);
                String[] record = parseEntry(entries.getProperty(key));
                if (!validName(name, pkg) || record == null) return false;
                File target = destination(pkg, name);
                if (!target.isFile() || target.length() != Long.parseLong(record[0])) return false;
                count++;
            }
            return count > 0;
        } catch (Exception e) {
            return false;
        }
    }

    private static MessageDigest sha256Digest() throws IOException {
        try {
            return MessageDigest.getInstance("SHA-256");
        } catch (NoSuchAlgorithmException e) {
            throw new IOException("SHA-256 unavailable", e);
        }
    }

    private static String toHex(byte[] bytes) {
        final char[] symbols = "0123456789abcdef".toCharArray();
        StringBuilder output = new StringBuilder(bytes.length * 2);
        for (byte b : bytes) {
            int v = b & 255;
            output.append(symbols[v >>> 4]).append(symbols[v & 15]);
        }
        return output.toString();
    }

    private static String hash(InputStream stream) throws IOException {
        MessageDigest sha = sha256Digest();
        byte[] block = new byte[BUFFER_SIZE];
        int read;
        while ((read = stream.read(block)) >= 0) {
            if (read > 0) sha.update(block, 0, read);
        }
        return toHex(sha.digest());
    }

    private static String fileHash(File file) throws IOException {
        try (InputStream stream = new FileInputStream(file)) {
            return hash(stream);
        }
    }

    public static boolean isReadyVerified(Context context, String pkg) {
        if (!isReadyQuick(context, pkg)) return false;
        Properties entries = readManifest(context, pkg);
        try {
            for (String key : entries.stringPropertyNames()) {
                if (!key.startsWith("obb.")) continue;
                String[] record = parseEntry(entries.getProperty(key));
                if (record == null || !record[1].equals(fileHash(destination(pkg, key.substring(4))))) {
                    return false;
                }
            }
            return true;
        } catch (IOException e) {
            Log.w(TAG, "Stored OBB hash verification failed", e);
            return false;
        }
    }

    public static boolean hasReadableHostObb(String pkg) {
        try {
            assertPackage(pkg);
            return !hostSources(pkg).isEmpty();
        } catch (IOException e) {
            return false;
        }
    }

    private interface Opener {
        InputStream open() throws IOException;
    }

    private static final class Source {
        final String name;
        final long size;
        final Opener opener;
        Source(String name, long size, Opener opener) {
            this.name = name;
            this.size = size;
            this.opener = opener;
        }
    }

    private static List<Source> hostSources(String pkg) {
        File dir = BEnvironment.getHostExternalObbDir(pkg);
        File[] files = dir.listFiles();
        if (files == null) return Collections.emptyList();
        List<Source> result = new ArrayList<>();
        for (final File file : files) {
            if (file.isFile() && file.canRead() && file.length() > 0
                    && validName(file.getName(), pkg)) {
                result.add(new Source(file.getName(), file.length(),
                        () -> new FileInputStream(file)));
            }
        }
        return result;
    }

    private static String documentName(Context context, Uri uri) {
        try (Cursor cursor = context.getContentResolver().query(
                uri, new String[]{OpenableColumns.DISPLAY_NAME}, null, null, null)) {
            if (cursor != null && cursor.moveToFirst()) {
                int column = cursor.getColumnIndex(OpenableColumns.DISPLAY_NAME);
                if (column >= 0) return cursor.getString(column);
            }
        } catch (Exception e) {
            Log.w(TAG, "Could not read document display name", e);
        }
        return null;
    }

    private static long documentSize(Context context, Uri uri) {
        try (Cursor cursor = context.getContentResolver().query(
                uri, new String[]{OpenableColumns.SIZE}, null, null, null)) {
            if (cursor != null && cursor.moveToFirst()) {
                int column = cursor.getColumnIndex(OpenableColumns.SIZE);
                if (column >= 0 && !cursor.isNull(column)) return cursor.getLong(column);
            }
        } catch (Exception ignored) {
        }
        return -1L;
    }

    public static int registerDocuments(Context context, String pkg, List<Uri> docs)
            throws IOException {
        assertPackage(pkg);
        if (context == null || docs == null || docs.isEmpty()) {
            throw new IOException("No OBB documents selected");
        }
        ContentResolver resolver = context.getContentResolver();
        SharedPreferences preferences = context.getSharedPreferences(PREFS, Context.MODE_PRIVATE);
        Set<String> selected = new TreeSet<>(preferences.getStringSet("uris_" + pkg,
                Collections.emptySet()));
        int added = 0;
        for (Uri uri : docs) {
            if (uri == null || !"content".equals(uri.getScheme())) continue;
            String name = documentName(context, uri);
            if (!validName(name, pkg)) {
                throw new IOException("Not a matching OBB for " + pkg + ": " + name);
            }
            // The system picker returns durable read grants for ACTION_OPEN_DOCUMENT.
            try {
                resolver.takePersistableUriPermission(uri, Intent.FLAG_GRANT_READ_URI_PERMISSION);
            } catch (SecurityException e) {
                throw new IOException("Please choose an OBB with the system Documents picker", e);
            }
            // Confirm the URI can actually be opened now, before saving it.
            try (InputStream in = resolver.openInputStream(uri)) {
                if (in == null || in.read() == -1) throw new IOException("Empty OBB: " + name);
            }
            if (selected.add(uri.toString())) added++;
            if (selected.size() > MAX_DOCUMENTS) {
                throw new IOException("Too many OBB documents selected (max " + MAX_DOCUMENTS + ")");
            }
        }
        if (selected.isEmpty()) throw new IOException("No valid game OBB documents");
        preferences.edit().putStringSet("uris_" + pkg, selected).apply();
        return added;
    }

    public static boolean hasRegisteredDocuments(Context context, String pkg) {
        if (context == null || pkg == null) return false;
        return !context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
                .getStringSet("uris_" + pkg, Collections.emptySet()).isEmpty();
    }

    private static List<Source> documentSources(Context context, String pkg) {
        Set<String> uris = context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
                .getStringSet("uris_" + pkg, Collections.emptySet());
        List<Source> result = new ArrayList<>();
        for (String stringUri : uris) {
            try {
                final Uri uri = Uri.parse(stringUri);
                String name = documentName(context, uri);
                if (!validName(name, pkg)) continue;
                final ContentResolver resolver = context.getContentResolver();
                try (InputStream input = resolver.openInputStream(uri)) {
                    if (input == null) continue;
                }
                result.add(new Source(name, documentSize(context, uri), () -> {
                    InputStream input = resolver.openInputStream(uri);
                    if (input == null) throw new IOException("No stream for OBB document " + uri);
                    return input;
                }));
            } catch (Exception e) {
                Log.w(TAG, "Saved OBB document is no longer readable", e);
            }
        }
        return result;
    }

    private static String sourceHash(Source source) throws IOException {
        try (InputStream input = source.opener.open()) {
            return hash(input);
        }
    }

    private static void copyAndVerify(Source source, File target, String expectedSha)
            throws IOException {
        File dir = target.getParentFile();
        if (dir == null || (!dir.isDirectory() && !dir.mkdirs())) {
            throw new IOException("Virtual OBB folder cannot be created: " + dir);
        }
        if (source.size > 0 && dir.getUsableSpace() < source.size + BUFFER_SIZE) {
            throw new IOException("Insufficient storage space to copy " + source.name);
        }
        File tmp = new File(dir, target.getName() + ".partial");
        if (tmp.exists() && !tmp.delete()) {
            throw new IOException("Cannot clear previous interrupted copy: " + tmp);
        }
        try {
            MessageDigest sha = sha256Digest();
            long copied = 0;
            try (InputStream input = source.opener.open();
                 FileOutputStream output = new FileOutputStream(tmp)) {
                byte[] block = new byte[BUFFER_SIZE];
                int size;
                while ((size = input.read(block)) >= 0) {
                    if (size == 0) continue;
                    output.write(block, 0, size);
                    sha.update(block, 0, size);
                    copied += size;
                }
                output.getFD().sync();
            }
            if (copied <= 0 || (source.size > 0 && copied != source.size)
                    || tmp.length() != copied
                    || !expectedSha.equals(toHex(sha.digest()))) {
                throw new IOException("OBB copy checksum or length mismatch: " + source.name);
            }
            // Keep the old file intact until the new file is completely verified.
            if (!tmp.renameTo(target)) {
                throw new IOException("Could not activate new virtual OBB: " + source.name);
            }
            if (target.length() != copied || !expectedSha.equals(fileHash(target))) {
                throw new IOException("OBB verification after copy failed: " + source.name);
            }
        } finally {
            if (tmp.exists()) tmp.delete();
        }
    }

    /**
     * Runs on a background thread. Finds OBB sources, syncs each game expansion
     * file to the exact BEnvironment virtual path, and updates a hash manifest.
     * Launch must not occur until this method completes successfully.
     */
    public static synchronized String sync(Context context, String pkg, Progress progress)
            throws IOException {
        if (context == null) throw new IOException("Missing application context");
        assertPackage(pkg);
        Context app = context.getApplicationContext();
        if (app == null) app = context;
        File targetDir = BEnvironment.getExternalObbDir(pkg);
        if (!targetDir.isDirectory() && !targetDir.mkdirs()) {
            throw new IOException("Virtual OBB location unavailable: " + targetDir);
        }

        Map<String, Source> sources = new LinkedHashMap<>();
        for (Source source : hostSources(pkg)) sources.put(source.name, source);
        // Manually provided matching game files are an explicit, durable fallback.
        // Prefer readable files from the actual host game when names collide.
        for (Source source : documentSources(app, pkg)) {
            if (!sources.containsKey(source.name)) sources.put(source.name, source);
        }

        if (sources.isEmpty()) {
            if (isReadyVerified(app, pkg)) {
                return "Existing virtual OBB verified: " + targetDir.getAbsolutePath();
            }
            throw new IOException(NEED_SOURCE + " Expected virtual path: "
                    + targetDir.getAbsolutePath());
        }

        Properties next = new Properties();
        int i = 0;
        for (Source source : sources.values()) {
            i++;
            if (progress != null) progress.update(35 + 50 * (i - 1) / sources.size(),
                    "Checking OBB " + i + "/" + sources.size(), source.name);
            String inputHash = sourceHash(source);
            File target = destination(pkg, source.name);
            if (!target.isFile() || target.length() <= 0
                    || (source.size > 0 && target.length() != source.size)
                    || !inputHash.equals(fileHash(target))) {
                if (progress != null) progress.update(35 + 50 * i / sources.size(),
                        "Copying missing or changed OBB", source.name);
                copyAndVerify(source, target, inputHash);
            }
            // copyAndVerify already hashes the staged file after replacement,
            // or the unchanged target's SHA-256 was checked above. Do not
            // re-read multi-gigabyte expansion files unnecessarily.
            if (target.length() <= 0 || (source.size > 0 && target.length() != source.size)) {
                throw new IOException("OBB not valid in virtual game path: " + target);
            }
            next.setProperty("obb." + source.name, target.length() + ":" + inputHash);
        }

        writeManifest(app, pkg, next);
        if (!isReadyQuick(app, pkg)) {
            throw new IOException("Virtual OBB manifest verification failed after sync");
        }
        if (progress != null) progress.update(95, "OBB verified", targetDir.getAbsolutePath());
        return "OBB checked in " + targetDir.getAbsolutePath();
    }

    public static String getVirtualPath(String pkg) {
        if (pkg == null) return "";
        return BEnvironment.getExternalObbDir(pkg).getAbsolutePath();
    }
}
