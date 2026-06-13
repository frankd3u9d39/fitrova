import { API_BASE_URL } from './apiClient';

export interface ChallengeParticipant {
  first_name: string;
  last_name: string;
  initials: string;
  color: string;
  is_me?: boolean;
  profile_picture?: string | null;
}

export interface Challenge {
  id: number;
  key: string;
  title: string;
  description: string;
  difficulty: 'Beginner' | 'Intermediate' | 'Advanced';
  duration: string;
  target_value: number;
  participants_count: number;
  joined: boolean;
  participants: ChallengeParticipant[];
}

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
  challenges: Challenge[];
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
  const today = new Date();
  const year = today.getFullYear();
  const month = String(today.getMonth() + 1).padStart(2, '0');
  const day = String(today.getDate()).padStart(2, '0');
  const dateStr = `${year}-${month}-${day}`;

  const response = await fetch(`${API_BASE_URL}/app/controllers/profile/log_weight.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ user_id: userId, weight, date: dateStr }),
  });
  const result = await response.json();
  if (result.status !== 'success') {
    throw new Error(result.message || 'Failed to log weight');
  }
};

export const joinChallenge = async (userId: number, challengeKey: string, action: 'join' | 'leave'): Promise<void> => {
  const response = await fetch(`${API_BASE_URL}/app/controllers/workout/join_challenge.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ user_id: userId, challenge_key: challengeKey, action }),
  });
  const result = await response.json();
  if (result.status !== 'success') {
    throw new Error(result.message || 'Failed to update challenge status');
  }
};
