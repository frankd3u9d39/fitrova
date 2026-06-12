import { API_BASE_URL } from './apiClient';

export interface ProfileStats {
  user: {
    first_name: string;
    last_name: string;
    email: string;
    motto: string;
    profile_picture: string | null;
    subscription_tier: string;
  };
  stats: {
    total_workouts: number;
    avg_duration: number;
    streak: number;
  };
  personal_records: Array<{
    exercise_name: string;
    max_weight: number;
  }>;
  recent_activities: Array<{
    title: string;
    time: string;
    icon: string;
    color: string;
    type: string;
  }>;
}

export const getProfileStats = async (userId: number): Promise<ProfileStats> => {
  try {
    const response = await fetch(
      `${API_BASE_URL}/app/controllers/profile/get_profile_stats.php?user_id=${userId}`
    );

    const result = await response.json();

    if (!response.ok || result.status !== 'success') {
      throw new Error(result.message || 'Failed to fetch profile stats');
    }

    return result.data;
  } catch (error) {
    console.error('Profile stats fetch error:', error);
    throw error;
  }
};

export interface UpdateProfileParams {
  user_id: number;
  first_name: string;
  last_name: string;
  email: string;
  motto: string;
  profile_picture?: string | null;
}

export const updateProfile = async (params: UpdateProfileParams): Promise<{ status: string; message: string }> => {
  try {
    const response = await fetch(`${API_BASE_URL}/app/controllers/profile/update_profile.php`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(params),
    });

    const result = await response.json();

    if (!response.ok || result.status !== 'success') {
      throw new Error(result.message || 'Failed to update profile');
    }

    return result;
  } catch (error) {
    console.error('Update profile error:', error);
    throw error;
  }
};
