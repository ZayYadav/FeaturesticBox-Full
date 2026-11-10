package com.featurestic.box.fake.service;

import android.content.ComponentName;
import android.content.Intent;
import android.content.pm.ActivityInfo;
import android.content.pm.ServiceInfo;
import android.os.Handler;
import android.os.IBinder;
import android.os.Message;

import androidx.annotation.NonNull;

import java.lang.reflect.Field;
import java.lang.reflect.Method;
import java.lang.reflect.Proxy;
import java.util.List;
import java.util.Map;
import java.util.concurrent.atomic.AtomicBoolean;

import black.android.app.ActivityThreadActivityClientRecordContext;
import black.android.app.BRActivityClient;
import black.android.app.BRActivityClientActivityClientControllerSingleton;
import black.android.app.BRActivityManagerNative;
import black.android.app.BRActivityThread;
import black.android.app.BRActivityThreadActivityClientRecord;
import black.android.app.BRActivityThreadCreateServiceData;
import black.android.app.BRActivityThreadH;
import black.android.app.BRIActivityManager;
import black.android.app.servertransaction.BRClientTransaction;
import black.android.app.servertransaction.BRLaunchActivityItem;
import black.android.app.servertransaction.LaunchActivityItemContext;
import black.android.os.BRHandler;
import com.featurestic.box.FeaturesticBoxCore;
import com.featurestic.box.app.BActivityThread;
import com.featurestic.box.compat.auth.GoogleSignInTokenHelper;
import com.featurestic.box.fake.hook.IInjectHook;
import com.featurestic.box.proxy.ProxyManifest;
import com.featurestic.box.proxy.record.ProxyActivityRecord;
import com.featurestic.box.utils.Slog;
import com.featurestic.box.utils.compat.BuildCompat;

/**
 * ActivityThread callback used to replace host stub activities with their
 * virtual targets before Android executes the launch transaction.
 *
 * Supports both Android 9–14 QPR1 (ClientTransaction.mActivityToken) and
 * Android 14 QPR2 / 15 / 16 (ActivityTransactionItem.mActivityToken / getActivityToken()).
 */
public class HCallbackStub implements IInjectHook, Handler.Callback {
    public static final String TAG = "HCallbackStub";

    private Handler.Callback mOtherCallback;
    private final AtomicBoolean mBeing = new AtomicBoolean(false);

    private Handler.Callback getHCallback() {
        return BRHandler.get(getH()).mCallback();
    }

    private Handler getH() {
        Object currentActivityThread = FeaturesticBoxCore.mainThread();
        return BRActivityThread.get(currentActivityThread).mH();
    }

    @Override
    public void injectHook() {
        mOtherCallback = getHCallback();
        if (mOtherCallback != null
                && (mOtherCallback == this
                || mOtherCallback.getClass().getName().equals(getClass().getName()))) {
            mOtherCallback = null;
        }
        BRHandler.get(getH())._set_mCallback(this);
    }

    @Override
    public boolean isBadEnv() {
        Handler.Callback hCallback = getHCallback();
        return hCallback != null && hCallback != this;
    }

    @Override
    public boolean handleMessage(@NonNull Message msg) {
        if (mBeing.getAndSet(true)) {
            return false;
        }

        try {
            if (BuildCompat.isPie()) {
                if (msg.what == BRActivityThreadH.get().EXECUTE_TRANSACTION()) {
                    try {
                        GoogleSignInTokenHelper.inspectClientTransaction(msg.obj);
                    } catch (Throwable ignored) {
                    }
                }
                if (msg.what == BRActivityThreadH.get().EXECUTE_TRANSACTION()
                        && handleLaunchActivity(msg.obj)) {
                    getH().sendMessageAtFrontOfQueue(Message.obtain(msg));
                    return true;
                }
            } else if (msg.what == BRActivityThreadH.get().LAUNCH_ACTIVITY()
                    && handleLaunchActivity(msg.obj)) {
                getH().sendMessageAtFrontOfQueue(Message.obtain(msg));
                return true;
            }

            if (msg.what == BRActivityThreadH.get().CREATE_SERVICE()) {
                return handleCreateService(msg.obj);
            }
            if (mOtherCallback != null) {
                return mOtherCallback.handleMessage(msg);
            }
            return false;
        } finally {
            mBeing.set(false);
        }
    }

    private Object getLaunchActivityItem(Object clientTransaction) {
        if (clientTransaction == null) {
            return null;
        }

        try {
            List<Object> callbacks = GoogleSignInTokenHelper.extractClientTransactionItems(clientTransaction);
            if (callbacks == null) {
                return null;
            }

            Class<?> launchClass = BRLaunchActivityItem.getRealClass();
            for (Object item : callbacks) {
                if (item == null) {
                    continue;
                }
                if ((launchClass != null && launchClass.isInstance(item))
                        || item.getClass().getName().endsWith(".LaunchActivityItem")) {
                    return item;
                }
            }
        } catch (Throwable error) {
            Slog.e(TAG, "Unable to inspect ClientTransaction launch item: " + error);
        }
        return null;
    }

    private static Intent extractStubIntent(Object launchItem) {
        if (launchItem == null) return null;
        try {
            Intent i = BRLaunchActivityItem.get(launchItem).mIntent();
            if (i != null) return i;
        } catch (Throwable ignored) {
        }
        Object fieldVal = getFieldValueHierarchy(launchItem, "mIntent");
        if (fieldVal instanceof Intent) {
            return (Intent) fieldVal;
        }
        try {
            Method m = launchItem.getClass().getMethod("getIntent");
            Object res = m.invoke(launchItem);
            if (res instanceof Intent) return (Intent) res;
        } catch (Throwable ignored) {
        }
        return null;
    }

    private static IBinder extractActivityToken(Object clientTransaction, Object launchItem) {
        if (clientTransaction != null) {
            try {
                IBinder t = BRClientTransaction.get(clientTransaction).mActivityToken();
                if (t != null) return t;
            } catch (Throwable ignored) {
            }
            try {
                Method m = clientTransaction.getClass().getMethod("getActivityToken");
                Object res = m.invoke(clientTransaction);
                if (res instanceof IBinder) return (IBinder) res;
            } catch (Throwable ignored) {
            }
            Object fVal = getFieldValueHierarchy(clientTransaction, "mActivityToken");
            if (fVal instanceof IBinder) return (IBinder) fVal;
        }
        if (launchItem != null) {
            try {
                Method m = launchItem.getClass().getMethod("getActivityToken");
                Object res = m.invoke(launchItem);
                if (res instanceof IBinder) return (IBinder) res;
            } catch (Throwable ignored) {
            }
            Object fVal = getFieldValueHierarchy(launchItem, "mActivityToken");
            if (fVal instanceof IBinder) return (IBinder) fVal;
        }
        return null;
    }

    @SuppressWarnings("unchecked")
    private static Object findLaunchingActivityRecord(IBinder token, Intent stubIntent) {
        Object mainThread = FeaturesticBoxCore.mainThread();
        if (mainThread == null) return null;
        if (token != null) {
            try {
                Object r = BRActivityThread.get(mainThread).getLaunchingActivity(token);
                if (r != null) return r;
            } catch (Throwable ignored) {
            }
        }
        try {
            Object mapObj = getFieldValueHierarchy(mainThread, "mLaunchingActivities");
            if (mapObj instanceof Map) {
                Map<Object, Object> map = (Map<Object, Object>) mapObj;
                if (token != null && map.containsKey(token)) {
                    return map.get(token);
                }
                if (map.size() == 1) {
                    return map.values().iterator().next();
                }
                for (Object record : map.values()) {
                    if (record == null) continue;
                    Object rIntent = getFieldValueHierarchy(record, "intent");
                    if (rIntent == stubIntent) {
                        return record;
                    }
                }
            }
        } catch (Throwable ignored) {
        }
        return null;
    }

    private Boolean handleLaunchActivity(Object client) {
        if (client == null) {
            return false;
        }

        Object launchItem;
        if (BuildCompat.isPie()) {
            launchItem = getLaunchActivityItem(client);
            if (launchItem == null) {
                return false;
            }
        } else {
            launchItem = client;
        }

        Intent stubIntent;
        IBinder token;
        if (BuildCompat.isPie()) {
            stubIntent = extractStubIntent(launchItem);
            token = extractActivityToken(client, launchItem);
        } else {
            ActivityThreadActivityClientRecordContext recordContext =
                    BRActivityThreadActivityClientRecord.get(launchItem);
            stubIntent = recordContext.intent();
            token = recordContext.token();
        }

        if (stubIntent == null) {
            Slog.e(TAG, "Ignoring launch transaction with an already-null Intent");
            return false;
        }

        ProxyActivityRecord stubRecord = ProxyActivityRecord.create(stubIntent);
        ActivityInfo activityInfo = stubRecord.mActivityInfo;
        if (activityInfo == null) {
            return false;
        }

        Intent targetIntent = stubRecord.mTarget;
        BActivityThread activityThread = BActivityThread.currentActivityThread();

        if (BuildCompat.isS() && !activityThread.ensureFrameworkInitialApplication()) {
            Slog.e(TAG, "Framework Application is not ready for virtual launch");
        }

        if (BActivityThread.getAppConfig() == null) {
            FeaturesticBoxCore.getBActivityManager().restartProcess(
                    activityInfo.packageName,
                    activityInfo.processName,
                    stubRecord.mUserId);

            Intent packageLaunchIntent = FeaturesticBoxCore.getBPackageManager()
                    .getLaunchIntentForPackage(activityInfo.packageName, stubRecord.mUserId);

            Intent restartTarget = packageLaunchIntent != null
                    ? packageLaunchIntent : targetIntent;
            if (restartTarget == null) {
                Slog.e(TAG, "Process restart has no safe virtual target; keeping host stub intact");
                return false;
            }

            stubIntent.setExtrasClassLoader(getClass().getClassLoader());
            ProxyActivityRecord.saveStub(
                    stubIntent,
                    restartTarget,
                    stubRecord.mActivityInfo,
                    stubRecord.mActivityRecord,
                    stubRecord.mUserId);
            writeLaunchRecord(launchItem, stubIntent, activityInfo, null);
            return true;
        }

        if (!activityThread.isInit()) {
            activityThread.bindApplication(
                    activityInfo.packageName, activityInfo.processName);

            if (!activityThread.isInit()) {
                Slog.e(TAG, "Guest Application bind did not reach a launch-ready state");
                return false;
            }
            // Fall through and rewrite launchItem + launchingRecord immediately in this pass!
            // Re-queuing EXECUTE_TRANSACTION on Android 12-16 can cause LaunchActivityItem
            // to use an unmodified ActivityClientRecord cached by preExecute().
        }

        if (!activityThread.ensureFrameworkInitialApplication()) {
            Slog.e(TAG, "Refusing virtual launch without a framework Application");
            return false;
        }

        if (targetIntent == null) {
            Slog.e(TAG, "Virtual stub is missing target Intent; refusing null launch rewrite");
            return false;
        }

        Object launchingRecord = null;
        if (BuildCompat.isS()) {
            launchingRecord = findLaunchingActivityRecord(token, stubIntent);
            if (token == null && launchingRecord != null) {
                Object recToken = getFieldValueHierarchy(launchingRecord, "token");
                if (recToken instanceof IBinder) {
                    token = (IBinder) recToken;
                }
            }
        }

        try {
            if (BActivityThread.getApplication() != null
                    && BActivityThread.getApplication().getClassLoader() != null) {
                targetIntent.setExtrasClassLoader(BActivityThread.getApplication().getClassLoader());
            }
        } catch (Throwable ignored) {
        }

        int taskId = resolveTaskIdSafely(token);
        FeaturesticBoxCore.getBActivityManager().onActivityCreated(
                taskId, token, stubRecord.mActivityRecord);

        if (BuildCompat.isS()) {
            writeLaunchRecord(launchItem, targetIntent, activityInfo, launchingRecord);
            checkActivityClient();
        } else if (BuildCompat.isPie()) {
            writeLaunchRecord(launchItem, targetIntent, activityInfo, null);
        } else {
            ActivityThreadActivityClientRecordContext recordContext =
                    BRActivityThreadActivityClientRecord.get(launchItem);
            recordContext._set_intent(targetIntent);
            recordContext._set_activityInfo(activityInfo);
        }
        return false;
    }

    private void writeLaunchRecord(
            Object launchItem,
            Intent targetIntent,
            ActivityInfo activityInfo,
            Object launchingRecord) {
        if (launchItem == null || targetIntent == null || activityInfo == null) {
            return;
        }

        if (BuildCompat.isPie()) {
            try {
                LaunchActivityItemContext launchContext = BRLaunchActivityItem.get(launchItem);
                launchContext._set_mIntent(targetIntent);
                launchContext._set_mInfo(activityInfo);
            } catch (Throwable ignored) {
            }
            setFieldValueHierarchy(launchItem, "mIntent", targetIntent);
            setFieldValueHierarchy(launchItem, "mInfo", activityInfo);
        } else {
            ActivityThreadActivityClientRecordContext recordContext =
                    BRActivityThreadActivityClientRecord.get(launchItem);
            recordContext._set_intent(targetIntent);
            recordContext._set_activityInfo(activityInfo);
        }

        if (launchingRecord != null) {
            Object pkgInfo = BActivityThread.currentActivityThread().getPackageInfo();
            try {
                ActivityThreadActivityClientRecordContext recordContext =
                        BRActivityThreadActivityClientRecord.get(launchingRecord);
                recordContext._set_intent(targetIntent);
                recordContext._set_activityInfo(activityInfo);
                if (pkgInfo != null) {
                    recordContext._set_packageInfo(pkgInfo);
                }
            } catch (Throwable ignored) {
            }
            setFieldValueHierarchy(launchingRecord, "intent", targetIntent);
            setFieldValueHierarchy(launchingRecord, "activityInfo", activityInfo);
            if (pkgInfo != null) {
                setFieldValueHierarchy(launchingRecord, "packageInfo", pkgInfo);
            }
        }
    }

    private static Object getFieldValueHierarchy(Object target, String fieldName) {
        if (target == null || fieldName == null) return null;
        Class<?> cls = target.getClass();
        while (cls != null && cls != Object.class) {
            try {
                Field f = cls.getDeclaredField(fieldName);
                f.setAccessible(true);
                return f.get(target);
            } catch (NoSuchFieldException ignored) {
                cls = cls.getSuperclass();
            } catch (Throwable ignored) {
                break;
            }
        }
        return null;
    }

    private static void setFieldValueHierarchy(Object target, String fieldName, Object value) {
        if (target == null || fieldName == null) return;
        Class<?> cls = target.getClass();
        while (cls != null && cls != Object.class) {
            try {
                Field f = cls.getDeclaredField(fieldName);
                f.setAccessible(true);
                f.set(target, value);
                return;
            } catch (NoSuchFieldException ignored) {
                cls = cls.getSuperclass();
            } catch (Throwable ignored) {
                break;
            }
        }
    }

    private boolean handleCreateService(Object data) {
        if (BActivityThread.getAppConfig() != null) {
            String appPackageName = BActivityThread.getAppPackageName();
            if (appPackageName == null || data == null) {
                return false;
            }

            ServiceInfo serviceInfo = BRActivityThreadCreateServiceData.get(data).info();
            if (serviceInfo == null || serviceInfo.name == null) {
                return false;
            }

            if (!serviceInfo.name.equals(ProxyManifest.getProxyService(BActivityThread.getAppPid()))
                    && !serviceInfo.name.equals(
                    ProxyManifest.getProxyJobService(BActivityThread.getAppPid()))) {
                Slog.d(TAG, "handleCreateService: " + data);
                Intent intent = new Intent();
                intent.setComponent(new ComponentName(appPackageName, serviceInfo.name));
                FeaturesticBoxCore.getBActivityManager().startService(
                        intent, null, false, BActivityThread.getUserId());
                return true;
            }
        }
        return false;
    }

    private int resolveTaskIdSafely(IBinder token) {
        if (token == null) return 0;
        try {
            Integer id = BRIActivityManager.get(BRActivityManagerNative.get().getDefault())
                    .getTaskForActivity(token, false);
            if (id != null && id != -1) {
                return id;
            }
        } catch (Throwable ignored) {
        }
        try {
            Object client = BRActivityClient.get().getActivityClientController();
            if (client != null) {
                Method m = client.getClass().getMethod("getTaskForActivity", IBinder.class, boolean.class);
                Object res = m.invoke(client, token, false);
                if (res instanceof Integer) {
                    return (Integer) res;
                }
            }
        } catch (Throwable ignored) {
        }
        return 0;
    }

    private void checkActivityClient() {
        try {
            Object activityClientController =
                    BRActivityClient.get().getActivityClientController();
            if (!(activityClientController instanceof Proxy)) {
                IActivityClientProxy proxy = new IActivityClientProxy(activityClientController);
                proxy.onlyProxy(true);
                proxy.injectHook();
                Object instance = BRActivityClient.get().getInstance();
                Object singleton = BRActivityClient.get(instance).INTERFACE_SINGLETON();
                BRActivityClientActivityClientControllerSingleton.get(singleton)
                        ._set_mKnownInstance(proxy.getProxyInvocation());
            }
        } catch (Throwable error) {
            Slog.e(TAG, "Unable to refresh ActivityClient hook: " + error);
        }
    }
}
