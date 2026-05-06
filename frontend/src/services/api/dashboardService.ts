import { API_BASE_URL } from './apiClient';

export interface DashboardData {
  user: {
    first_name: string;
    last_name: string;
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
  try {
    const response = await fetch(`${API_BASE_URL}/app/controllers/workout/get_dashboard_data.php`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ user_id: userId }),
    });

    const result = await response.json();

    if (!response.ok || result.status !== 'success') {
      throw new Error(result.message || 'Failed to fetch dashboard data');
    }

    return result.data;
  } catch (error) {
    console.error('Dashboard data fetch error:', error);
    throw error;
  }
};
