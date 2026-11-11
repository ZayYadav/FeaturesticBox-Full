package com.featurestic.box.app;

import android.animation.Animator;
import android.animation.AnimatorSet;
import android.animation.ObjectAnimator;
import android.animation.ValueAnimator;
import android.app.Activity;
import android.content.Intent;
import android.content.pm.PackageInfo;
import android.graphics.Color;
import android.graphics.drawable.Drawable;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.view.View;
import android.view.WindowManager;
import android.view.animation.DecelerateInterpolator;
import android.view.animation.LinearInterpolator;
import android.widget.ImageView;
import android.widget.TextView;

import androidx.annotation.Nullable;

import java.util.ArrayList;
import java.util.List;

import com.featurestic.box.FeaturesticAntiCrackVault;
import com.featurestic.box.FeaturesticBoxCore;
import com.featurestic.box.R;
import com.featurestic.box.utils.Slog;
import com.featurestic.box.utils.compat.FeaturesticLogCleaner;
import com.featurestic.box.utils.compat.FeaturesticMemoryLockGuard;
import com.featurestic.box.utils.compat.FeaturesticZeroCopyLinker;
import com.featurestic.box.utils.compat.ThermalAndFrameGuard;

public class LauncherActivity extends Activity {

    public static final String TAG = "FeaturesticLaunch";
    public static final String KEY_INTENT = "launch_intent";
    public static final String KEY_PKG = "launch_pkg";
    public static final String KEY_USER_ID = "launch_user_id";

    private final Handler mainHandler = new Handler(Looper.getMainLooper());
    private final List<Animator> runningAnimators = new ArrayList<>();
    private boolean isRunning = false;
    private boolean isLaunchDispatched = false;

    public static void launch(Intent intent, int userId) {
        Intent splash = new Intent();
        splash.setClass(FeaturesticBoxCore.getContext(), LauncherActivity.class);
        splash.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);

        String pkg = intent != null ? intent.getPackage() : null;
        if ((pkg == null || pkg.isEmpty()) && intent != null && intent.getComponent() != null) {
            pkg = intent.getComponent().getPackageName();
        }

        splash.putExtra(KEY_INTENT, intent);
        splash.putExtra(KEY_PKG, pkg);
        splash.putExtra(KEY_USER_ID, userId);
        try {
            if (!FeaturesticAntiCrackVault.stampLaunchIntentProof(FeaturesticBoxCore.getContext(), splash, pkg, userId)) {
                return;
            }
        } catch (Throwable ignored) {
        }
        FeaturesticBoxCore.getContext().startActivity(splash);
    }

    @Override
    protected void onCreate(@Nullable Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        try {
            getWindow().clearFlags(WindowManager.LayoutParams.FLAG_SECURE);
            getWindow().setStatusBarColor(Color.BLACK);
            getWindow().setNavigationBarColor(Color.BLACK);
        } catch (Throwable ignored) {
        }

        Intent sourceIntent = getIntent();
        if (sourceIntent == null) {
            finish();
            return;
        }

        Intent launchIntent = sourceIntent.getParcelableExtra(KEY_INTENT);
        String packageName = sourceIntent.getStringExtra(KEY_PKG);
        int userId = sourceIntent.getIntExtra(KEY_USER_ID, 0);
        if (launchIntent == null) {
            Slog.e(TAG, "Launch intent is missing");
            finish();
            return;
        }

        if (packageName == null || packageName.isEmpty()) {
            packageName = launchIntent.getPackage();
        }
        if ((packageName == null || packageName.isEmpty()) && launchIntent.getComponent() != null) {
            packageName = launchIntent.getComponent().getPackageName();
        }
        if (packageName == null || packageName.isEmpty()) {
            Slog.e(TAG, "Package name is missing");
            finish();
            return;
        }

        if (!FeaturesticAntiCrackVault.verifyLaunchIntentProof(this, sourceIntent, packageName, userId)) {
            finish();
            return;
        }
        if (! com.featurestic.box.FeaturesticRemoteConfigGuard.isLaunchAllowedForGame(this, packageName, userId)) {
            com.featurestic.box.FeaturesticRemoteConfigGuard.showMaintenanceNoticeIfNeeded(this);
            return;
        }

        PackageInfo packageInfo = FeaturesticBoxCore.getBPackageManager()
                .getPackageInfo(packageName, 0, userId);
        if (packageInfo == null) {
            Slog.e(TAG, packageName + " is not installed");
            finish();
            return;
        }

        setContentView(R.layout.activity_launcher);
        try {
            ThermalAndFrameGuard.applyToActivityWindow(this);
            FeaturesticLogCleaner.startAutoCleanerForGame(this, packageName, userId);
            FeaturesticMemoryLockGuard.engageMemoryLockForProcess(this);
        } catch (Throwable ignored) {
        }

        bindAppIdentity(packageInfo);
        startBrandAnimations();
        scheduleLaunch(launchIntent, userId);
    }

    private void bindAppIdentity(PackageInfo packageInfo) {
        try {
            Drawable icon = packageInfo.applicationInfo
                    .loadIcon(FeaturesticBoxCore.getPackageManager());
            CharSequence label = packageInfo.applicationInfo
                    .loadLabel(FeaturesticBoxCore.getPackageManager());

            ImageView iconView = findViewById(R.id.iv_icon);
            TextView nameView = findViewById(R.id.tv_app_name);
            if (iconView != null) {
                iconView.setImageDrawable(icon);
            }
            if (nameView != null) {
                nameView.setText(label);
            }
        } catch (Throwable ignored) {
        }
    }

    private void startBrandAnimations() {
        try {
            View brandPanel = findViewById(R.id.brand_panel);
            View appCard = findViewById(R.id.app_identity_card);
            View ring = findViewById(R.id.brand_ring);
            View glow = findViewById(R.id.runtime_glow);
            View statusDot = findViewById(R.id.runtime_status_dot);

            if (brandPanel != null) {
                brandPanel.setAlpha(0f);
                brandPanel.setScaleX(0.8f);
                brandPanel.setScaleY(0.8f);
                AnimatorSet entrance = new AnimatorSet();
                entrance.playTogether(
                        ObjectAnimator.ofFloat(brandPanel, View.ALPHA, 0f, 1f),
                        ObjectAnimator.ofFloat(brandPanel, View.SCALE_X, 0.8f, 1f),
                        ObjectAnimator.ofFloat(brandPanel, View.SCALE_Y, 0.8f, 1f));
                entrance.setDuration(420L);
                entrance.setInterpolator(new DecelerateInterpolator(1.5f));
                trackAndStart(entrance);
            }

            if (appCard != null) {
                appCard.setAlpha(0f);
                appCard.setTranslationY(24f);
                AnimatorSet appEntrance = new AnimatorSet();
                appEntrance.playTogether(
                        ObjectAnimator.ofFloat(appCard, View.ALPHA, 0f, 1f),
                        ObjectAnimator.ofFloat(appCard, View.TRANSLATION_Y, 24f, 0f));
                appEntrance.setStartDelay(90L);
                appEntrance.setDuration(320L);
                appEntrance.setInterpolator(new DecelerateInterpolator());
                trackAndStart(appEntrance);
            }

            if (ring != null) {
                ObjectAnimator orbit = ObjectAnimator.ofFloat(ring, View.ROTATION, 0f, 360f);
                orbit.setDuration(7600L);
                orbit.setRepeatCount(ValueAnimator.INFINITE);
                orbit.setInterpolator(new LinearInterpolator());
                trackAndStart(orbit);
            }

            if (glow != null) {
                AnimatorSet breathingGlow = new AnimatorSet();
                ObjectAnimator glowX = ObjectAnimator.ofFloat(glow, View.SCALE_X, 0.93f, 1.07f);
                ObjectAnimator glowY = ObjectAnimator.ofFloat(glow, View.SCALE_Y, 0.93f, 1.07f);
                ObjectAnimator glowAlpha = ObjectAnimator.ofFloat(glow, View.ALPHA, 0.4f, 0.85f);
                for (ObjectAnimator animator : new ObjectAnimator[]{glowX, glowY, glowAlpha}) {
                    animator.setRepeatCount(ValueAnimator.INFINITE);
                    animator.setRepeatMode(ValueAnimator.REVERSE);
                }
                breathingGlow.playTogether(glowX, glowY, glowAlpha);
                breathingGlow.setDuration(1450L);
                trackAndStart(breathingGlow);
            }

            if (statusDot != null) {
                ObjectAnimator pulse = ObjectAnimator.ofFloat(statusDot, View.ALPHA, 0.25f, 1f);
                pulse.setDuration(520L);
                pulse.setRepeatCount(ValueAnimator.INFINITE);
                pulse.setRepeatMode(ValueAnimator.REVERSE);
                trackAndStart(pulse);
            }
        } catch (Throwable ignored) {
        }
    }

    private void scheduleLaunch(final Intent launchIntent, final int userId) {
        final TextView status = findViewById(R.id.launch_status);
        mainHandler.postDelayed(new Runnable() {
            @Override
            public void run() {
                animateStatus(status, "UNLOCKING 120HZ HARDWARE DISPLAY MODE");
            }
        }, 25L);
        mainHandler.postDelayed(new Runnable() {
            @Override
            public void run() {
                animateStatus(status, "MOUNTING HIGH-SPEED GAME SANDBOX");
            }
        }, 80L);
        mainHandler.postDelayed(new Runnable() {
            @Override
            public void run() {
                new Thread(new Runnable() {
                    @Override
                    public void run() {
                        try {
                            String pkg = launchIntent.getPackage();
                            if ((pkg == null || pkg.isEmpty()) && launchIntent.getComponent() != null) {
                                pkg = launchIntent.getComponent().getPackageName();
                            }
                            if (!FeaturesticAntiCrackVault.ensureEmbeddedSdkKeyAuthenticatedSync(LauncherActivity.this, 4500L)) {
                                mainHandler.post(new Runnable() {
                                    @Override
                                    public void run() {
                                        finish();
                                    }
                                });
                                return;
                            }
                            mainHandler.post(() -> animateStatus(status, "CHECKING GAME OBB"));
                            // This runs on FeaturesticLaunch worker thread, not UI.
                            // Uses the SDK's host-path, storage-volume, MediaStore
                            // and previously granted document fallbacks. On failure,
                            // do not launch a virtual game with a missing/partial OBB.
                            com.featurestic.sdk.ObbSyncManager.sync(
                                    LauncherActivity.this, pkg, (percent, title, detail) -> {
                                        final int safe = Math.max(0, Math.min(100, percent));
                                        mainHandler.post(() -> animateStatus(
                                                status, "OBB " + safe + "%  " + title));
                                    });
                            if (!com.featurestic.sdk.ObbSyncManager.isReadyVerified(
                                    LauncherActivity.this, pkg)) {
                                throw new java.io.IOException("Virtual OBB checksum verification failed");
                            }
                            mainHandler.post(() -> animateStatus(status, "GAME OBB VERIFIED"));
                            isLaunchDispatched = true;
                            FeaturesticBoxCore.getBActivityManager().startActivity(launchIntent, userId);
                        } catch (Throwable throwable) {
                            Slog.e(TAG, "Unable to start isolated activity: " + throwable.getMessage());
                            mainHandler.post(new Runnable() {
                                @Override
                                public void run() {
                                    finish();
                                }
                            });
                        }
                    }
                }, "FeaturesticLaunch").start();
            }
        }, 150L);
    }

    private void animateStatus(final TextView view, final String value) {
        if (view == null || isFinishing() || isDestroyed()) {
            return;
        }
        view.setText(value);
    }

    private void trackAndStart(Animator animator) {
        runningAnimators.add(animator);
        animator.start();
    }

    @Override
    protected void onPause() {
        if (isLaunchDispatched) {
            isRunning = true;
        }
        super.onPause();
    }

    @Override
    protected void onResume() {
        super.onResume();
        if (isRunning) {
            finish();
        }
    }

    @Override
    

    protected void onDestroy() {
        mainHandler.removeCallbacksAndMessages(null);
        for (Animator animator : runningAnimators) {
            animator.cancel();
        }
        runningAnimators.clear();
        super.onDestroy();
        try { FeaturesticAntiCrackVault.zeroizeOffsetsInMemory(); } catch (Throwable ignored) {}
    }
}
