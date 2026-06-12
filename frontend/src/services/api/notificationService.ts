import { endpoints } from './apiClient';

export interface Notification {
  id: number;
  insight_text: string;
  insight_type: 'motivation' | 'warning' | 'achievement' | 'tip';
  is_read: boolean;
  created_at: string;
}

export interface GetNotificationsResponse {
  status: string;
  unread_count: number;
  data: Notification[];
}

export const notificationService = {
  getNotifications: async (userId: number): Promise<{ notifications: Notification[]; unreadCount: number }> => {
    try {
      const response = await fetch(`${endpoints.getNotifications}?user_id=${userId}`);
      if (!response.ok) {
        throw new Error(`Failed to fetch notifications: ${response.status}`);
      }
      
      const result: GetNotificationsResponse = await response.json();
      if (result.status !== 'success') {
        throw new Error(result.status || 'Failed to fetch notifications');
      }
      
      return {
        notifications: result.data || [],
        unreadCount: result.unread_count || 0
      };
    } catch (error) {
      console.error('getNotifications error:', error);
      return { notifications: [], unreadCount: 0 };
    }
  },

  markAsRead: async (userId: number, notificationId?: number): Promise<void> => {
    try {
      const response = await fetch(endpoints.markNotificationRead, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          user_id: userId,
          notification_id: notificationId,
        }),
      });

      if (!response.ok) {
        throw new Error(`Failed to mark notifications as read: ${response.status}`);
      }

      const result = await response.json();
      if (result.status !== 'success') {
        throw new Error(result.message || 'Failed to mark notifications as read');
      }
    } catch (error) {
      console.error('markAsRead error:', error);
    }
  }
};
