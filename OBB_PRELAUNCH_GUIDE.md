# OBB pre-launch sync: Android / virtual filesystem

This SDK stages Android expansion OBB files for the installed host game into the
**virtual game's expansion folder** returned by
\`com.featurestic.sdk.FeaturesticBox.getVirtualObbPath(packageName)\`.

## Execution order

1. Host game installed? 
2. Virtual game installed? Install it if needed.
3. Look for *readable* matching main/patch OBBs in the host's
   \`/storage/emulated/0/Android/obb/<game-package>/\`.
4. If the host folder cannot be read, use previously user-authorized
   \`ACTION_OPEN_DOCUMENT\` .obb URIs (e.g. expansion files in Downloads).
5. Stream to \`<virtualDir>/<main-or-patch>.<version>.<package>.obb.partial\`;
   compare bytes and SHA-256, atomically rename into the virtual folder, and
   retain a rollback backup while replacing an existing file.
6. Store an app-private SHA-256/size manifest for each required file.
7. Do not report setup complete or start the virtual game until all expected
   OBB inventory entries are present and checked.
8. Repeat OBB source / target checking before **every** loader game launch.
   If the source is unavailable, verify the last imported inventory by SHA-256.

SDK API:

\`\`\`java
FeaturesticBox.setupGameAsync(context, gamePackage, 0, new FeaturesticBox.SetupCallback() {
    @Override public void onProgress(int p, String title, String detail) {
        // Update UI on the main thread
    }
    @Override public void onComplete(boolean success, String message) {
        // Only call FeaturesticBox.startGame on success.
    }
});
\`\`\`

Call the asynchronous preflight again before each launch, even when
\`hasVirtualObb\` returns true. The quick status is for UI; the SDK worker
performs full hash verification.

## Expected path

The virtual folder is determined by `BEnvironment.getExternalObbDir(pkg)`.
On a writable external volume it now **prefers the Loader's app-owned**
`<loader-external-files>/SdCard/Android/obb/<pkg>`, obtained through
`Context.getExternalFilesDir("SdCard")`. If that API is unavailable, it can
try the legacy shared `/storage/emulated/0/SdCard/Android/obb/<pkg>`.

`RCore` maps guest `/sdcard/Android/obb/<pkg>` and
`/storage/emulated/0/Android/obb/<pkg>` to this **same** virtual folder.
Previously copied expansion files in the legacy shared `SdCard` directory are
probed as migration candidates.

Automatic candidates, all subject to Android access controls: host
`Android/obb/<pkg>`, own OBB directories (`Context.getObbDirs()`), own external
files directories, storage roots discovered by `StorageManager.getStorageVolumes()`,
readable Download(s), Documents, OBB and GameOBB folders, MediaStore indexed
Download files and previously saved user-authorized `ACTION_OPEN_DOCUMENT`
URIs. If one candidate fails, another readable source is tried.

No root, device-owner permission, shell trick or access-control bypass is used.

## Android 11+ limitation

The storage access framework does not permit selecting other apps'
\`Android/obb\` trees on supported Android versions. An app cannot guarantee
directly reading another app's protected OBB file, even when the game itself
is installed. If no readable host source or previously verified virtual OBB
exists, the SDK returns \`OBB_SOURCE_REQUIRED\`.

The Loader lets the user select their own matching \`main.VERSION.PACKAGE.obb\`
and \`patch.VERSION.PACKAGE.obb\` files from a supported accessible folder.
No root commands, privileged permissions, Android storage bypasses, or
unverified “mount ready” claims are involved.

For actual delivery, validate a full install/copy/launch cycle on a device
running the intended Android version. CI can confirm compilation, not whether
a particular device permits access to another app's OBB.
