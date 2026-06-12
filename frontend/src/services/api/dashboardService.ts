import { API_BASE_URL } from './apiClient';

export interface DashboardData {
  user: {
    first_name: string;
    last_name: string;
    profile_picture: string | null;
  };
  health_score: number;
  calories: {
    consumed: number;
    goal: number;
  };
  weight: {
    current: number | null;
    target: number | null;
    history: Array<{
      weight: number;
      recorded_date: string;
    }>;
  };
  today_workout: {
    name: string;
    duration: number;
  } | null;
  insight: {
    text: string;
    type: string;
  } | null;
}

export const getDashboardData = async (userId: number): Promise<DashboardData> => {
  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), 15000); // 15s timeout

  try {
    const response = await fetch(`${API_BASE_URL}/app/controllers/workout/get_dashboard_data.php`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ user_id: userId }),
      signal: controller.signal,
    });

    clearTimeout(timeoutId);
    const result = await response.json();

    if (!response.ok || result.status !== 'success') {
      throw new Error(result.message || 'Failed to fetch dashboard data');
    }

    return result.data;
  } catch (error: any) {
    clearTimeout(timeoutId);
    if (error?.name === 'AbortError') {
      throw new Error('Request timed out. Please check your network connection.');
    }
    console.error('Dashboard data fetch error:', error);
    throw error;
  }
};


export const logWeight = async (userId: number, weight: number): Promise<void> => {
  const response = await fetch(`${API_BASE_URL}/app/controllers/profile/log_weight.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ user_id: userId, weight, date: new Date().toISOString().split('T')[0] }),
  });
  const result = await response.json();
  if (result.status !== 'success') {
    throw new Error(result.message || 'Failed to log weight');
  }
};
