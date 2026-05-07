// AppNavigator.tsx - Navigation Logic Final
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
import { ActiveWorkoutScreen } from '../screens/workout/ActiveWorkoutScreen/ActiveWorkoutScreen';
import { ScheduleScreen } from '../screens/workout/ScheduleScreen/ScheduleScreen';
import { FormCheckScreen } from '../screens/workout/FormCheckScreen/FormCheckScreen';
import { RoutineLibraryScreen } from '../screens/workout/RoutineLibraryScreen/RoutineLibraryScreen';
import { SettingsScreen } from '../screens/profile/SettingsScreen/SettingsScreen';
import { FoodScanScreen } from '../screens/nutrition/FoodScanScreen/FoodScanScreen';
import { FoodResultScreen } from '../screens/nutrition/FoodResultScreen/FoodResultScreen';
import { NutritionHistoryScreen } from '../screens/nutrition/NutritionHistoryScreen/NutritionHistoryScreen';
import VerifyEmailScreen from '../screens/onboarding/RegisterScreen/VerifyEmailScreen';
import { RootStackParamList, MainTabParamList } from './types';

const Stack = createNativeStackNavigator<RootStackParamList>();
const Tab = createBottomTabNavigator<MainTabParamList>();

const MainTabs = ({ route }: any) => {
  const firstName = route.params?.firstName || 'User';
  const userId = route.params?.userId || 1;
  
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
        initialParams={{ firstName, userId }}
      />
      <Tab.Screen 
        name="Workout" 
        component={WorkoutScreen} 
        initialParams={{ userId }}
      />
      <Tab.Screen 
        name="Nutrition" 
        component={NutritionScreen} 
        initialParams={{ userId }}
      />
      <Tab.Screen 
        name="Profile" 
        component={ProfileScreen} 
        initialParams={{ userId, firstName }}
      />
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
        <Stack.Screen name="EmailVerification" component={VerifyEmailScreen} />
        <Stack.Screen name="Personalization" component={PersonalizationScreen} />
        <Stack.Screen name="GoalSetting" component={GoalSettingScreen} />
        <Stack.Screen name="Restrictions" component={RestrictionsScreen} />
        <Stack.Screen name="Main" component={MainTabs} />
        <Stack.Screen name="Achievements" component={AchievementsScreen} />
        <Stack.Screen name="ActiveWorkout" component={ActiveWorkoutScreen} options={{ presentation: 'fullScreenModal' }} />
        <Stack.Screen name="Schedule" component={ScheduleScreen} />
        <Stack.Screen name="FormCheck" component={FormCheckScreen} />
        <Stack.Screen name="RoutineLibrary" component={RoutineLibraryScreen} />
        <Stack.Screen name="Settings" component={SettingsScreen} />
        <Stack.Screen name="FoodScan" component={FoodScanScreen} />
        <Stack.Screen name="FoodResult" component={FoodResultScreen} />
        <Stack.Screen name="NutritionHistory" component={NutritionHistoryScreen} />
      </Stack.Navigator>
    </NavigationContainer>
  );
};
