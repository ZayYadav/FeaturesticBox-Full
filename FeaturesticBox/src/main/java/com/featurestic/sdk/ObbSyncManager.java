package com.featurestic.sdk;

import android.content.ContentResolver;
import android.content.ContentUris;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.database.Cursor;
import android.net.Uri;
import android.os.Build;
import android.os.Environment;
import android.os.storage.StorageManager;
import android.os.storage.StorageVolume;
import android.provider.OpenableColumns;
import android.provider.MediaStore;
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
import java.util.LinkedHashSet;
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

    /** Old SDKs may have left symlinks pointing at restricted host OBB files. */
    private static boolean isSymbolicLink(File file) {
        try {
            android.system.Os.readlink(file.getAbsolutePath());
            return true;
        } catch (Exception notALink) {
            return false;
        }
    }

    private static File destination(String pkg, String filename) {
        File target = new File(BEnvironment.getExternalObbDir(pkg), filename);
        File backup = new File(target.getParentFile(), filename + ".backup");
        if (!target.exists() && backup.isFile()) {
            if (!backup.renameTo(target)) {
                Log.w(TAG, "Unable to restore previous expansion file: " + backup);
            }
        }
        return target;
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
                if (!target.isFile() || isSymbolicLink(target)
                        || target.length() != Long.parseLong(record[0])) return false;
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
            File dir = BEnvironment.getHostExternalObbDir(pkg);
            return dir != null && scanObbDir(dir, pkg).size() > 0;
        } catch (Throwable ignored) {
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

    private static List<Source> scanObbDir(File dir, String pkg) {
        if (dir == null || !dir.isDirectory()) return Collections.emptyList();
        final File[] files;
        try {
            files = dir.listFiles();
        } catch (SecurityException blocked) {
            return Collections.emptyList();
        }
        if (files == null) return Collections.emptyList();
        List<Source> matches = new ArrayList<>();
        for (final File file : files) {
            try {
                if (!validName(file.getName(), pkg) || !file.isFile() || file.length() <= 0) continue;
                // isReadable/canRead alone does not prove Android FUSE lets a stream open.
                try (InputStream probe = new FileInputStream(file)) {
                    if (probe.read() < 0) continue;
                }
                matches.add(new Source(file.getName(), file.length(),
                        () -> new FileInputStream(file)));
            } catch (IOException | SecurityException e) {
                Log.d(TAG, "OBB source not readable in " + dir, e);
            }
        }
        return matches;
    }

    private static void addDirectory(LinkedHashSet<String> directories, File candidate) {
        if (candidate == null) return;
        try {
            directories.add(candidate.getCanonicalPath());
        } catch (IOException | SecurityException e) {
            directories.add(candidate.getAbsolutePath());
        }
    }

    /**
     * Candidate paths are sourced from documented Android storage APIs, not
     * a magic permission or a bypass. Every candidate is probed for readability.
     * The host path has first priority; following paths are user-owned exports,
     * app-specific staging, and removable-storage volumes.
     */
    private static List<Source> hostSources(Context context, String pkg) {
        LinkedHashSet<String> paths = new LinkedHashSet<>();
        addDirectory(paths, BEnvironment.getHostExternalObbDir(pkg));

        LinkedHashSet<String> roots = new LinkedHashSet<>();
        try {
            addDirectory(roots, Environment.getExternalStorageDirectory());
        } catch (SecurityException ignored) {}

        if (context != null) {
            try {
                File[] ownDirs = context.getObbDirs();
                if (ownDirs != null) {
                    for (File own : ownDirs) {
                        addDirectory(paths, own); // our app's own OBB area is accessible
                        addDirectory(paths, own == null ? null : new File(own, pkg));
                    }
                }
                File[] extDirs = context.getExternalFilesDirs(null);
                if (extDirs != null) {
                    for (File dir : extDirs) {
                        if (dir == null) continue;
                        addDirectory(paths, new File(dir, "obb"));
                        addDirectory(paths, new File(dir, "obb/" + pkg));
                        addDirectory(paths, new File(dir, pkg));
                        String full = dir.getAbsolutePath();
                        int cut = full.indexOf("/Android/data/");
                        if (cut > 0) addDirectory(roots, new File(full.substring(0, cut)));
                    }
                }
            } catch (Exception e) {
                Log.d(TAG, "External-files directory discovery unavailable", e);
            }

            // Framework storage volumes include additional SD cards and USB media.
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
                try {
                    StorageManager manager = (StorageManager)
                            context.getSystemService(Context.STORAGE_SERVICE);
                    if (manager != null) {
                        for (StorageVolume volume : manager.getStorageVolumes()) {
                            File root = volume.getDirectory();
                            if (root != null) addDirectory(roots, root);
                        }
                    }
                } catch (Exception e) {
                    Log.d(TAG, "StorageManager volume discovery unavailable", e);
                }
            }
            addDirectory(paths, new File(context.getFilesDir(), "obb/" + pkg));
            addDirectory(paths, new File(context.getFilesDir(), "obb"));
        }

        for (String root : roots) {
            File base = new File(root);
            addDirectory(paths, new File(base, "Android/obb/" + pkg));
            // Migration route: older Loader builds kept guest expansion files
            // under a top-level SdCard directory. Treat that as an accessible
            // source when moving to the app-owned virtual storage root.
            addDirectory(paths, new File(base, "SdCard/Android/obb/" + pkg));
            addDirectory(paths, new File(base, "Download"));
            addDirectory(paths, new File(base, "Download/" + pkg));
            addDirectory(paths, new File(base, "Downloads"));
            addDirectory(paths, new File(base, "Downloads/" + pkg));
            addDirectory(paths, new File(base, "Documents"));
            addDirectory(paths, new File(base, "Documents/" + pkg));
            addDirectory(paths, new File(base, "OBB"));
            addDirectory(paths, new File(base, "OBB/" + pkg));
            addDirectory(paths, new File(base, "GameOBB"));
            addDirectory(paths, new File(base, "GameOBB/" + pkg));
            addDirectory(paths, new File(base, "FeaturesticBox/obb/" + pkg));
        }
        // Preserve alternatives for the *same* filename. A first source may
        // disappear or become unreadable while synchronization is running.
        List<Source> discovered = new ArrayList<>();
        for (String path : paths) {
            for (Source candidate : scanObbDir(new File(path), pkg)) {
                Log.i(TAG, "Candidate OBB source: " + path + "/" + candidate.name);
                discovered.add(candidate);
            }
        }
        return discovered;
    }

    /**
     * MediaStore is another framework-mediated route for indexed downloads
     * that the calling application is authorized to read. OEM providers can
     * decline these queries; that is a normal fallback miss, not a bypass.
     */
    private static List<Source> mediaStoreSources(Context context, String pkg) {
        if (context == null || Build.VERSION.SDK_INT < Build.VERSION_CODES.Q) {
            return Collections.emptyList();
        }
        final ContentResolver resolver = context.getContentResolver();
        final String[] projection = {
                MediaStore.MediaColumns._ID,
                MediaStore.MediaColumns.DISPLAY_NAME,
                MediaStore.MediaColumns.SIZE
        };
        List<Source> result = new ArrayList<>();
        Uri[] collections = {
                MediaStore.Downloads.EXTERNAL_CONTENT_URI,
                MediaStore.Files.getContentUri("external")
        };
        LinkedHashSet<String> collectedUris = new LinkedHashSet<>();
        for (Uri collection : collections) {
            try (Cursor cursor = resolver.query(collection, projection,
                    MediaStore.MediaColumns.DISPLAY_NAME + " LIKE ?",
                    new String[]{"%." + pkg + ".obb"}, null)) {
                if (cursor == null) continue;
                int idCol = cursor.getColumnIndexOrThrow(MediaStore.MediaColumns._ID);
                int nameCol = cursor.getColumnIndexOrThrow(MediaStore.MediaColumns.DISPLAY_NAME);
                int sizeCol = cursor.getColumnIndexOrThrow(MediaStore.MediaColumns.SIZE);
                int examined = 0;
                while (cursor.moveToNext() && examined++ < 64) {
                    final String name = cursor.getString(nameCol);
                    if (!validName(name, pkg)) continue;
                    final long size = cursor.isNull(sizeCol) ? -1L : cursor.getLong(sizeCol);
                    if (size == 0) continue;
                    final Uri contentUri = ContentUris.withAppendedId(
                            collection, cursor.getLong(idCol));
                    if (!collectedUris.add(contentUri.toString())) continue;
                    try (InputStream sample = resolver.openInputStream(contentUri)) {
                        if (sample == null || sample.read() < 0) continue;
                    } catch (IOException | SecurityException cannotAccess) {
                        continue;
                    }
                    result.add(new Source(name, size, () -> {
                        InputStream in = resolver.openInputStream(contentUri);
                        if (in == null) throw new IOException("OBB MediaStore source unavailable");
                        return in;
                    }));
                }
            } catch (Exception e) {
                Log.d(TAG, "MediaStore OBB lookup not permitted for " + collection, e);
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
            // Preserve the last known-good file if the destination filesystem
            // refuses rename-over-existing, or verification later fails.
            File backup = new File(dir, target.getName() + ".backup");
            boolean hadOld = target.exists();
            if (hadOld) {
                if (backup.exists() && !backup.delete()) {
                    throw new IOException("Cannot clear old OBB backup: " + backup);
                }
                if (!target.renameTo(backup)) {
                    throw new IOException("Cannot back up old virtual OBB: " + target);
                }
            }
            if (!tmp.renameTo(target)) {
                if (hadOld) backup.renameTo(target);
                throw new IOException("Could not activate new virtual OBB: " + source.name);
            }
            if (isSymbolicLink(target) || target.length() != copied
                    || !expectedSha.equals(fileHash(target))) {
                target.delete();
                if (hadOld) backup.renameTo(target);
                throw new IOException("OBB verification after copy failed: " + source.name);
            }
            if (hadOld && backup.exists() && !backup.delete()) {
                Log.w(TAG, "Old OBB backup could not be removed: " + backup);
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
    private static void addCandidate(Map<String, List<Source>> candidates, Source source) {
        if (source == null) return;
        List<Source> options = candidates.get(source.name);
        if (options == null) {
            options = new ArrayList<>();
            candidates.put(source.name, options);
        }
        // Bound the work to avoid untrusted providers advertising thousands of
        // duplicate candidates for the same expansion filename.
        if (options.size() < 32) options.add(source);
    }

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

        Map<String, List<Source>> sources = new LinkedHashMap<>();
        for (Source option : hostSources(app, pkg)) addCandidate(sources, option);
        // Persistent, explicitly user-selected grants take priority over
        // possible stale MediaStore index results for the same OBB filename.
        for (Source option : documentSources(app, pkg)) addCandidate(sources, option);
        // ContentResolver may expose indexed Downloads inaccessible by File.
        for (Source option : mediaStoreSources(app, pkg)) addCandidate(sources, option);

        if (sources.isEmpty()) {
            if (isReadyVerified(app, pkg)) {
                return "Existing virtual OBB verified: " + targetDir.getAbsolutePath();
            }
            throw new IOException(NEED_SOURCE + " Expected virtual path: "
                    + targetDir.getAbsolutePath());
        }

        Properties next = new Properties();
        int checked = 0;
        for (Map.Entry<String, List<Source>> entry : sources.entrySet()) {
            checked++;
            String name = entry.getKey();
            if (progress != null) progress.update(
                    35 + 50 * (checked - 1) / sources.size(),
                    "Verifying OBB " + checked + "/" + sources.size(), name);
            IOException lastError = null;
            boolean verified = false;
            for (Source option : entry.getValue()) {
                try {
                    String expected = sourceHash(option);
                    File target = destination(pkg, name);
                    if (!target.isFile() || isSymbolicLink(target) || target.length() <= 0
                            || (option.size > 0 && target.length() != option.size)
                            || !expected.equals(fileHash(target))) {
                        if (progress != null) progress.update(
                                35 + 50 * checked / sources.size(),
                                "Copying game expansion file", name);
                        copyAndVerify(option, target, expected);
                    }
                    if (target.length() <= 0
                            || (option.size > 0 && target.length() != option.size)) {
                        throw new IOException("OBB size validation failed for " + name);
                    }
                    next.setProperty("obb." + name, target.length() + ":" + expected);
                    verified = true;
                    break;
                } catch (IOException | SecurityException denied) {
                    Log.w(TAG, "OBB source failed, checking next authorized source: " + name, denied);
                    lastError = new IOException("OBB candidate inaccessible: " + name, denied);
                }
            }
            if (!verified) {
                // Do not launch with a partial or unverified copy. The existing
                // validated inventory may be used only if its hashes still match.
                if (isReadyVerified(app, pkg)) {
                    return "Existing virtual OBB verified after source failures: "
                            + targetDir.getAbsolutePath();
                }
                throw new IOException("All accessible OBB candidates failed for " + name
                        + ". " + NEED_SOURCE, lastError);
            }
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
