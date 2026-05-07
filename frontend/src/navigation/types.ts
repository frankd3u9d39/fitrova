// frontend/src/navigation/types.ts
import { Workout } from '../services/api/workoutService';

export type RootStackParamList = {
  Welcome: undefined;
  SignUp: { verified?: boolean; email?: string };
  Login: undefined;
  EmailVerification: { email: string; firstName: string; signupFlow?: boolean };
  Personalization: { userId: number; firstName: string };
  GoalSetting: {
    userId: number;
    age: string;
    gender: string;
    height: string;
    weight: string;
    activityLevel: string;
    goal?: string;
    firstName: string;
    hasEquipment?: boolean;
  };
  Restrictions: {
    userId: number;
    age: string;
    gender: string;
    height: string;
    weight: string;
    activityLevel: string;
    goal?: string;
    selectedGoal: string;
    targetWeight: string;
    targetDate: string;
    firstName: string;
    hasEquipment?: boolean;
  };
  Main: { firstName: string; userId: number };
  Achievements: undefined;
  ActiveWorkout: { workout: Workout; userId: number };
  Schedule: { userId: number };
  FormCheck: undefined;
  RoutineLibrary: undefined;
  Settings: undefined;
  FoodScan: { userId: number };
  FoodResult: { imageUri: string; base64: string; userId: number };
  NutritionHistory: { userId: number };
  Profile: { userId: number };
};

export type MainTabParamList = {
  Home: { firstName: string; userId: number };
  Workout: { userId: number };
  Nutrition: { userId: number };
  Profile: { userId: number; firstName: string };
};
