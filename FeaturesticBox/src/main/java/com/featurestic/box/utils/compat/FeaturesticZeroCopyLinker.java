package com.featurestic.box.utils.compat;

import android.content.Context;
import android.util.Log;

import com.featurestic.box.FeaturesticBoxCore;
import com.featurestic.sdk.ObbSyncManager;

import java.io.IOException;

/**
 * Compatibility entry point for older SDK callers.
 *
 * Former versions only created symlinks and could report success without a
 * readable OBB. Delegate to the SDK's verified multi-source copier instead.
 * This call performs real disk IO and MUST run on a worker thread.
 */
public final class FeaturesticZeroCopyLinker {
    private static final String TAG = "FeaturesticObbLinker";

    private FeaturesticZeroCopyLinker() { }

    public static int linkGameObbAndMapPaks(String packageName, int userId) {
        Context host = FeaturesticBoxCore.getContext();
        if (host == null || packageName == null || packageName.trim().isEmpty()) {
            return 0;
        }
        try {
            // BEnvironment's guest root is shared by virtual slots; the copier
            // resolves this path and never writes into the host game's OBB folder.
            ObbSyncManager.sync(host, packageName, null);
            return ObbSyncManager.isReadyVerified(host, packageName) ? 1 : 0;
        } catch (IOException | SecurityException error) {
            Log.w(TAG, "Authorized source OBB unavailable; use a user-granted document", error);
            return 0;
        }
    }
}
