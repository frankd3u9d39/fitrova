import React from 'react';
import { NavigationContainer } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { Ionicons } from '@expo/vector-icons';
import { theme } from '../theme';
import { DynamicTabBar } from '../components/navigation/DynamicTabBar';

// Placeholder screen imports
import { WelcomeScreen } from '../screens/onboarding/WelcomeScreen/WelcomeScreen';
import { SignUpScreen } from '../screens/onboarding/RegisterScreen/SignUpScreen';
import { LoginScreen } from '../screens/onboarding/LoginScreen/LoginScreen';
import { PersonalizationScreen } from '../screens/onboarding/UserSetupScreen/PersonalizationScreen';
import { GoalSettingScreen } from '../screens/onboarding/UserSetupScreen/GoalSettingScreen';
import { RestrictionsScreen } from '../screens/onboarding/UserSetupScreen/RestrictionsScreen';
import { DashboardScreen } from '../screens/home/HomeDashboardScreen/DashboardScreen';
import { WorkoutScreen } from '../screens/workout/WorkoutScreen/WorkoutScreen';
import { NutritionScreen } from '../screens/nutrition/NutritionScreen/NutritionScreen';
import { ProfileScreen } from '../screens/profile/ProfileScreen/ProfileScreen';
import { AchievementsScreen } from '../screens/profile/AchievementsScreen/AchievementsScreen';

export type RootStackParamList = {
  Welcome: undefined;
  SignUp: undefined;
  Login: undefined;
  Personalization: { userId: number; firstName: string };
  GoalSetting: {
    userId: number;
    age: string;
    gender: string;
    height: string;
    weight: string;
    activityLevel: string;
    goal: string;
    firstName: string;
  };
  Restrictions: {
    userId: number;
    age: string;
    gender: string;
    height: string;
    weight: string;
    activityLevel: string;
    goal: string;
    selectedGoal: string;
    targetWeight: string;
    targetDate: string;
    firstName: string;
  };
  Main: { firstName: string };
  Achievements: undefined;
};

export type MainTabParamList = {
  Home: { firstName: string };
  Workout: undefined;
  Nutrition: undefined;
  Profile: undefined;
};

const Stack = createNativeStackNavigator<RootStackParamList>();
const Tab = createBottomTabNavigator<MainTabParamList>();

const MainTabs = ({ route }: any) => {
  const firstName = route.params?.firstName || 'User';
  
  return (
    <Tab.Navigator
      id="mainTabNavigator"
      tabBar={(props) => <DynamicTabBar {...props} />}
      screenOptions={{
        headerShown: false,
      }}
    >
      <Tab.Screen 
        name="Home" 
        component={DashboardScreen} 
        initialParams={{ firstName }}
      />
      <Tab.Screen name="Workout" component={WorkoutScreen} />
      <Tab.Screen name="Nutrition" component={NutritionScreen} />
      <Tab.Screen name="Profile" component={ProfileScreen} />
    </Tab.Navigator>
  );
};

export const AppNavigator = () => {
  return (
    <NavigationContainer>
      <Stack.Navigator
        id="rootStackNavigator"
        screenOptions={{
          headerShown: false,
          contentStyle: { backgroundColor: theme.colors.background },
        }}
      >
        <Stack.Screen name="Welcome" component={WelcomeScreen} />
        <Stack.Screen name="SignUp" component={SignUpScreen} />
        <Stack.Screen name="Login" component={LoginScreen} />
        <Stack.Screen name="Personalization" component={PersonalizationScreen} />
        <Stack.Screen name="GoalSetting" component={GoalSettingScreen} />
        <Stack.Screen name="Restrictions" component={RestrictionsScreen} />
        <Stack.Screen name="Main" component={MainTabs} />
        <Stack.Screen name="Achievements" component={AchievementsScreen} />
      </Stack.Navigator>
    </NavigationContainer>
  );
};
