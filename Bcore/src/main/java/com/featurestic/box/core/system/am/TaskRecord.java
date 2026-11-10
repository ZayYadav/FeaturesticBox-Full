package com.featurestic.box.core.system.am;

import android.content.Intent;
import android.os.IBinder;

import java.util.LinkedList;
import java.util.List;

public class TaskRecord {
    public int id;
    public int userId;
    public String taskAffinity;
    public Intent rootIntent;
    public final List<ActivityRecord> activities = new LinkedList<>();

    public TaskRecord(int id, int userId, String taskAffinity) {
        this.id = id;
        this.userId = userId;
        this.taskAffinity = taskAffinity;
    }

    private static boolean isRecordProcessDead(ActivityRecord record) {
        if (record == null) return true;
        if (record.finished) return true;
        try {
            if (record.processRecord != null && record.processRecord.bActivityThread != null) {
                IBinder binder = record.processRecord.bActivityThread.asBinder();
                if (binder != null && !binder.isBinderAlive()) {
                    record.finished = true;
                    return true;
                }
            }
        } catch (Throwable ignored) {
        }
        return false;
    }

    public boolean needNewTask() {
        for (ActivityRecord activity : activities) {
            if (!isRecordProcessDead(activity) && !activity.finished) {
                return false;
            }
        }
        return true;
    }

    public void addTopActivity(ActivityRecord record) {
        activities.add(record);
    }

    public void removeActivity(ActivityRecord record) {
        activities.remove(record);
    }

    public ActivityRecord getTopActivityRecord() {
        for (int i = activities.size() - 1; i >= 0; i--) {
            ActivityRecord activityRecord = activities.get(i);
            if (!isRecordProcessDead(activityRecord) && !activityRecord.finished) {
                return activityRecord;
            }
        }
        return null;
    }
}
