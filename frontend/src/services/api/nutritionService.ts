import { endpoints } from './apiClient';

export interface Meal {
  id: string;
  meal_name: string;
  meal_time: string;
  meal_type: 'breakfast' | 'lunch' | 'dinner' | 'snack';
  calories: number;
  protein: number;
  carbs: number;
  fats: number;
  items: FoodItem[];
}

export interface FoodItem {
  name: string;
  amount: string;
  calories: number;
}

export interface NutritionData {
  goals: {
    calories: number;
    protein: number;
    carbs: number;
    fats: number;
  };
  totals: {
    calories: number;
    protein: number;
    carbs: number;
    fats: number;
  };
  remaining: {
    calories: number;
    protein: number;
    carbs: number;
    fats: number;
  };
  meals: Meal[];
}

export const nutritionService = {
  getNutritionData: async (userId: number): Promise<NutritionData> => {
    try {
      const response = await fetch(endpoints.getNutrition, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ user_id: userId }),
      });

      const result = await response.json();

      if (result.status === 'success') {
        return result.data;
      } else {
        throw new Error(result.message || 'Failed to fetch nutrition data');
      }
    } catch (error) {
      console.error('Error in getNutritionData:', error);
      throw error;
    }
  },

  logMeal: async (data: any): Promise<any> => {
    try {
      const response = await fetch(endpoints.logMeal, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(data),
      });

      const result = await response.json();

      if (result.status === 'success') {
        return result.data;
      } else {
        throw new Error(result.message || 'Failed to log meal');
      }
    } catch (error) {
      console.error('Error in logMeal:', error);
      throw error;
    }
  },

  scanMeal: async (base64Image: string): Promise<any> => {
    try {
      const response = await fetch(endpoints.scanMeal, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ image: base64Image }),
      });

      const result = await response.json();

      if (result.status === 'success') {
        return result.data;
      } else {
        throw new Error(result.message || 'Failed to scan meal');
      }
    } catch (error) {
      console.error('Error in scanMeal:', error);
      throw error;
    }
  },
};
