import { endpoints } from './apiClient';

export type Category = 'Training' | 'Nutrition' | 'Milestones';

export interface Achievement {
  id: string;
  title: string;
  description: string;
  icon: string;
  category: Category;
  unlocked: boolean;
  color: string;
}

export const getAchievements = async (userId: number): Promise<Achievement[]> => {
  try {
    const response = await fetch(`${endpoints.getAchievements}?user_id=${userId}`);
    const result = await response.json();

    if (result.status === 'success') {
      return result.data;
    } else {
      throw new Error(result.message || 'Failed to fetch achievements');
    }
  } catch (error) {
    console.error('Error fetching achievements:', error);
    throw error;
  }
};
