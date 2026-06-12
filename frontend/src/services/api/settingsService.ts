import { endpoints } from './apiClient';

export const changePassword = async (
  userId: number,
  currentPassword: string,
  newPassword: string
): Promise<void> => {
  const response = await fetch(endpoints.changePassword, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ user_id: userId, current_password: currentPassword, new_password: newPassword }),
  });
  const result = await response.json();
  if (result.status !== 'success') {
    throw new Error(result.message || 'Failed to change password');
  }
};

export interface UserPreferences {
  unit_preference?: 'metric' | 'imperial';
  notification_enabled?: boolean;
  language?: string;
}

export const savePreferences = async (
  userId: number,
  prefs: UserPreferences
): Promise<void> => {
  const response = await fetch(endpoints.savePreferences, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      user_id: userId,
      ...prefs,
      notification_enabled: prefs.notification_enabled !== undefined
        ? (prefs.notification_enabled ? 1 : 0)
        : undefined,
    }),
  });
  const result = await response.json();
  if (result.status !== 'success') {
    throw new Error(result.message || 'Failed to save preferences');
  }
};
