import * as Notifications from 'expo-notifications';
import { Platform } from 'react-native';

// Configure notification behavior for when the app is active
Notifications.setNotificationHandler({
  handleNotification: async () => ({
    shouldShowAlert: true,
    shouldPlaySound: true,
    shouldSetBadge: false,
    shouldShowBanner: true,
    shouldShowList: true,
  }),
});

export const localNotificationService = {
  /**
   * Requests permission to show notifications to the user.
   * Sets up Android notification channels for SDK compatibility.
   */
  requestPermissions: async (): Promise<boolean> => {
    try {
      const { status: existingStatus } = await Notifications.getPermissionsAsync();
      let finalStatus = existingStatus;

      if (existingStatus !== 'granted') {
        const { status } = await Notifications.requestPermissionsAsync();
        finalStatus = status;
      }

      if (finalStatus !== 'granted') {
        console.log('[Local Notifications] Permission denied');
        return false;
      }

      if (Platform.OS === 'android') {
        await Notifications.setNotificationChannelAsync('default', {
          name: 'default',
          importance: Notifications.AndroidImportance.MAX,
          vibrationPattern: [0, 250, 250, 250],
          lightColor: '#10B981',
        });
      }

      return true;
    } catch (error) {
      console.error('[Local Notifications] Failed to get permissions:', error);
      return false;
    }
  },

  /**
   * Cancels all currently scheduled/pending notifications.
   */
  cancelAllNotifications: async () => {
    try {
      await Notifications.cancelAllScheduledNotificationsAsync();
      console.log('[Local Notifications] Cancelled all scheduled reminders');
    } catch (error) {
      console.error('[Local Notifications] Failed to cancel notifications:', error);
    }
  },

  /**
   * Schedules a daily reminder to complete the workout plan.
   * Triggers everyday at 9:00 AM local time.
   */
  scheduleDailyReminder: async () => {
    try {
      await Notifications.scheduleNotificationAsync({
        content: {
          title: "Today's Workout Awaits! 🏋️‍♂️",
          body: "Don't break your streak! Tap here to generate and complete today's personalized workout plan.",
          sound: true,
          priority: Notifications.AndroidNotificationPriority.HIGH,
        },
        trigger: {
          hour: 9,
          minute: 0,
          repeats: true,
        } as any,
      });
      console.log('[Local Notifications] Scheduled daily reminder at 09:00');
    } catch (error) {
      console.error('[Local Notifications] Failed to schedule daily reminder:', error);
    }
  },

  /**
   * Schedules a reminder that fires 24 hours after the last app activity.
   */
  scheduleInactivityReminder: async () => {
    try {
      await Notifications.scheduleNotificationAsync({
        content: {
          title: "We miss you! ❤️",
          body: "You haven't checked your workout plans today. Stay on track and lock in your session now!",
          sound: true,
          priority: Notifications.AndroidNotificationPriority.HIGH,
        },
        trigger: {
          seconds: 86400, // 24 hours
        } as any,
      });
      console.log('[Local Notifications] Scheduled inactivity reminder for 24 hours from now');
    } catch (error) {
      console.error('[Local Notifications] Failed to schedule inactivity reminder:', error);
    }
  },

  /**
   * Clears old scheduled alerts and schedules fresh ones.
   */
  resetReminders: async () => {
    const hasPermission = await localNotificationService.requestPermissions();
    if (!hasPermission) return;

    await localNotificationService.cancelAllNotifications();
    await localNotificationService.scheduleDailyReminder();
    await localNotificationService.scheduleInactivityReminder();
  },
};
