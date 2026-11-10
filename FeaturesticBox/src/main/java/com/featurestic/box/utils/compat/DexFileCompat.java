package com.featurestic.box.utils.compat;

import java.io.File;
import java.lang.reflect.Array;
import java.lang.reflect.Constructor;
import java.util.ArrayList;
import java.util.List;

import dalvik.system.DexFile;
import com.featurestic.box.utils.Reflector;

public class DexFileCompat {

    public static List<Long> getCookies(ClassLoader classLoader) {
        List<Long> cookies = new ArrayList<>();
        List<DexFile> dexFiles = getDexFiles(classLoader);
        for (DexFile dexFile : dexFiles) {
            cookies.addAll(getCookies(dexFile));
        }
        return cookies;
    }

    public static List<Long> getCookies(DexFile dexFile) {
        List<Long> cookies = new ArrayList<>();
        if (dexFile == null)
            return cookies;
        try {
            Object object = Reflector.with(dexFile)
                    .field("mCookie")
                    .get();
            if (BuildCompat.isM()) {
                for (long l : (long[]) object) {
                    cookies.add(l);
                }
            } else {
                cookies.add((long) object);
            }
        } catch (Exception e) {
            e.printStackTrace();
        }
        return cookies;
    }

    @SuppressWarnings("unchecked")
    public static void addNativeLibraryDirectories(ClassLoader classLoader, File... dirs) {
        if (classLoader == null || dirs == null || dirs.length == 0) return;
        Object dexPathList = getDexPathList(classLoader);
        if (dexPathList == null) return;
        List<File> addedDirs = new ArrayList<>();
        try {
            Object nativeLibDirsObj = Reflector.with(dexPathList).field("nativeLibraryDirectories").get();
            if (nativeLibDirsObj instanceof List) {
                List<File> nativeLibDirs = (List<File>) nativeLibDirsObj;
                for (File dir : dirs) {
                    if (dir == null) continue;
                    if (!dir.exists()) dir.mkdirs();
                    if (!nativeLibDirs.contains(dir)) {
                        nativeLibDirs.add(0, dir);
                        addedDirs.add(dir);
                    }
                }
            }
        } catch (Throwable ignored) {
        }
        if (addedDirs.isEmpty()) return;
        try {
            Object existingElements = Reflector.with(dexPathList).field("nativeLibraryPathElements").get();
            if (existingElements != null && existingElements.getClass().isArray()) {
                Class<?> elemClass = existingElements.getClass().getComponentType();
                Constructor<?> ctor = elemClass.getDeclaredConstructor(File.class);
                ctor.setAccessible(true);
                int oldLen = Array.getLength(existingElements);
                Object newArray = Array.newInstance(elemClass, oldLen + addedDirs.size());
                for (int i = 0; i < addedDirs.size(); i++) {
                    Array.set(newArray, i, ctor.newInstance(addedDirs.get(i)));
                }
                System.arraycopy(existingElements, 0, newArray, addedDirs.size(), oldLen);
                Reflector.with(dexPathList).field("nativeLibraryPathElements").set(newArray);
            }
        } catch (Throwable ignored) {
        }
    }

    private static List<DexFile> getDexFiles(ClassLoader classLoader) {
        List<DexFile> dexFiles = new ArrayList<>();
        Object[] dexElements = getDexElements(classLoader);
        for (Object dexElement : dexElements) {
            try {
                dexFiles.add(Reflector.with(dexElement)
                        .field("dexFile")
                        .<DexFile>get());
            } catch (Exception e) {
                e.printStackTrace();
            }
        }
        return dexFiles;
    }

    private static Object[] getDexElements(ClassLoader classLoader) {
        Object dexPathList = getDexPathList(classLoader);
        if (dexPathList == null) {
            return new Object[]{};
        }
        try {
            return Reflector.with(dexPathList)
                    .field("dexElements")
                    .get();
        } catch (Exception e) {
            e.printStackTrace();
        }
        return new Object[]{};
    }

    private static Object getDexPathList(ClassLoader classLoader) {
        try {
            return Reflector.on("dalvik.system.BaseDexClassLoader")
                    .field("pathList")
                    .get(classLoader);
        } catch (Exception e) {
            e.printStackTrace();
        }
        return null;
    }
}
