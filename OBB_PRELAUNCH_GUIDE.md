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

The virtual folder is determined by \`BEnvironment.getExternalObbDir(pkg)\`:
usually \`/storage/emulated/0/SdCard/Android/obb/<pkg>\`, or the Android-managed
\`<loader-external-files>/SdCard/Android/obb/<pkg>\` fallback when shared storage
cannot be written. \`RCore\` maps guest \`/sdcard/Android/obb/<pkg>\` and
\`/storage/emulated/0/Android/obb/<pkg>\` to this **same** virtual directory.

The real host folder is **never** used as a guest-path fallback. This removes
false “success” when the guest could not access the original OBB.

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
