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

export interface AIFoodRecommendation {
  name: string;
  reason: string;
  calories: number;
  protein: number;
  carbs: number;
  fats: number;
  description?: string;
  prep_time?: string;
  ingredients?: string[];
  benefits?: string[];
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
    burned?: number;
  };
  remaining: {
    calories: number;
    protein: number;
    carbs: number;
    fats: number;
  };
  meals: Meal[];
  latest_insight?: {
    text: string;
    type: 'warning' | 'tip';
  } | null;
}

export const nutritionService = {
  getNutritionData: async (userId: number, history: boolean = false): Promise<NutritionData> => {
    try {
      const response = await fetch(endpoints.getNutrition, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ user_id: userId, history }),
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

  scanMeal: async (base64Image: string, userId: number): Promise<any> => {
    try {
      const response = await fetch(endpoints.scanMeal, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ image: base64Image, user_id: userId }),
      });

      const result = await response.json();

      if (response.status === 403 || result.status === 'subscription_locked') {
        throw result;
      }

      if (response.ok && result.status === 'success') {
        return result.data;
      } else {
        throw new Error(result.message || 'Failed to scan meal');
      }
    } catch (error: any) {
      if (error && (error.status === 'subscription_locked' || error.statusCode === 403)) {
        console.log('Subscription lock triggered (Expected):', error.message || 'Trial used.');
      } else {
        console.error('Error in scanMeal:', error);
      }
      throw error;
    }
  },

  getAIFoodRecommendations: async (userId: number): Promise<AIFoodRecommendation[]> => {
    try {
      const response = await fetch(endpoints.getAIFoodRecommendations, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ user_id: userId }),
      });

      const result = await response.json();

      if (response.status === 403 || result.status === 'subscription_locked') {
        throw result;
      }

      if (response.ok && result.status === 'success') {
        return result.data;
      } else {
        throw new Error(result.message || 'Failed to fetch AI recommendations');
      }
    } catch (error: any) {
      if (error && (error.status === 'subscription_locked' || error.statusCode === 403)) {
        console.log('Subscription lock triggered (Expected) in getAIFoodRecommendations:', error.message || 'Trial used.');
      } else {
        console.error('Error in getAIFoodRecommendations:', error);
      }
      throw error;
    }
  },

  dismissInsight: async (userId: number): Promise<any> => {
    try {
      const response = await fetch(endpoints.dismissInsight, {
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
        throw new Error(result.message || 'Failed to dismiss insight');
      }
    } catch (error) {
      console.error('Error in dismissInsight:', error);
      throw error;
    }
  },
};
