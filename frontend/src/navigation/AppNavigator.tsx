// AppNavigator.tsx - Navigation Logic Final
import React, { useState, useEffect } from 'react';
import { ActivityIndicator, View } from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
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
import { ForgotPasswordScreen } from '../screens/onboarding/LoginScreen/ForgotPasswordScreen';
import { PersonalizationScreen } from '../screens/onboarding/UserSetupScreen/PersonalizationScreen';
import { GoalSettingScreen } from '../screens/onboarding/UserSetupScreen/GoalSettingScreen';
import { RestrictionsScreen } from '../screens/onboarding/UserSetupScreen/RestrictionsScreen';
import { SubscriptionSelectionScreen } from '../screens/onboarding/UserSetupScreen/SubscriptionSelectionScreen';
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
import { EditProfileScreen } from '../screens/profile/EditProfileScreen/EditProfileScreen';
import { YouTubeAnalysisScreen } from '../screens/workout/YouTubeAnalysisScreen/YouTubeAnalysisScreen';
import { FoodScanScreen } from '../screens/nutrition/FoodScanScreen/FoodScanScreen';
import { FoodResultScreen } from '../screens/nutrition/FoodResultScreen/FoodResultScreen';
import { NutritionHistoryScreen } from '../screens/nutrition/NutritionHistoryScreen/NutritionHistoryScreen';
import { MealLogScreen } from '../screens/nutrition/MealLogScreen/MealLogScreen';
import VerifyEmailScreen from '../screens/onboarding/RegisterScreen/VerifyEmailScreen';
import { NotificationsScreen } from '../screens/profile/NotificationsScreen/NotificationsScreen';
import { ChallengeCommunityScreen } from '../screens/home/ChallengeCommunityScreen';
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
  const [isLoading, setIsLoading] = useState(true);
  const [initialRouteName, setInitialRouteName] = useState<'Welcome' | 'Main' | 'Personalization' | 'GoalSetting' | 'Restrictions' | 'SubscriptionSelection'>('Welcome');
  const [initialParams, setInitialParams] = useState<any>(null);

  useEffect(() => {
    const checkSession = async () => {
      try {
        const savedSession = await AsyncStorage.getItem('user_session');
        if (savedSession) {
          const session = JSON.parse(savedSession);
          const { id, firstName, surveyStep, profile } = session;
          
          if (surveyStep === 'Complete') {
            setInitialRouteName('Main');
            setInitialParams({ firstName, userId: id });
          } else if (surveyStep === 'Personalization') {
            setInitialRouteName('Personalization');
            setInitialParams({ userId: id, firstName });
          } else if (surveyStep === 'GoalSetting') {
            setInitialRouteName('GoalSetting');
            setInitialParams({ 
              userId: id,
              firstName,
              age: profile?.age,
              gender: profile?.gender,
              height: profile?.height,
              weight: profile?.weight,
              activityLevel: profile?.activityLevel,
              goal: profile?.goal
            });
          } else if (surveyStep === 'Restrictions') {
            setInitialRouteName('Restrictions');
            setInitialParams({ 
              userId: id,
              firstName,
              age: profile?.age,
              gender: profile?.gender,
              height: profile?.height,
              weight: profile?.weight,
              activityLevel: profile?.activityLevel,
              goal: profile?.goal,
              selectedGoal: profile?.selectedGoal,
              targetWeight: profile?.targetWeight,
              targetDate: profile?.targetDate
            });
          } else if (surveyStep === 'SubscriptionSelection') {
            setInitialRouteName('SubscriptionSelection');
            setInitialParams({ userId: id, firstName });
          }
        }
      } catch (error) {
        console.error('Failed to load session:', error);
      } finally {
        setIsLoading(false);
      }
    };

    checkSession();
  }, []);

  if (isLoading) {
    return (
      <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: theme.colors.background }}>
        <ActivityIndicator size="large" color={theme.colors.primary} />
      </View>
    );
  }

  return (
    <NavigationContainer>
      <Stack.Navigator
        id="rootStackNavigator"
        initialRouteName={initialRouteName}
        screenOptions={{
          headerShown: false,
          contentStyle: { backgroundColor: theme.colors.background },
        }}
      >
        <Stack.Screen name="Welcome" component={WelcomeScreen} />
        <Stack.Screen name="SignUp" component={SignUpScreen} />
        <Stack.Screen name="Login" component={LoginScreen} />
        <Stack.Screen name="ForgotPassword" component={ForgotPasswordScreen} />
        <Stack.Screen name="EmailVerification" component={VerifyEmailScreen} />
        <Stack.Screen name="Personalization" component={PersonalizationScreen} initialParams={initialRouteName === 'Personalization' ? initialParams : undefined} />
        <Stack.Screen name="GoalSetting" component={GoalSettingScreen} initialParams={initialRouteName === 'GoalSetting' ? initialParams : undefined} />
        <Stack.Screen name="Restrictions" component={RestrictionsScreen} initialParams={initialRouteName === 'Restrictions' ? initialParams : undefined} />
        <Stack.Screen name="SubscriptionSelection" component={SubscriptionSelectionScreen} initialParams={initialRouteName === 'SubscriptionSelection' ? initialParams : undefined} />
        <Stack.Screen name="Main" component={MainTabs} initialParams={initialRouteName === 'Main' ? initialParams : undefined} />
        <Stack.Screen name="Achievements" component={AchievementsScreen} />
        <Stack.Screen name="ActiveWorkout" component={ActiveWorkoutScreen} options={{ presentation: 'fullScreenModal' }} />
        <Stack.Screen name="Schedule" component={ScheduleScreen} />
        <Stack.Screen name="FormCheck" component={FormCheckScreen} />
        <Stack.Screen name="RoutineLibrary" component={RoutineLibraryScreen} />
        <Stack.Screen name="Settings" component={SettingsScreen} />
        <Stack.Screen name="EditProfile" component={EditProfileScreen} />
        <Stack.Screen name="FoodScan" component={FoodScanScreen} />
        <Stack.Screen name="YouTubeAnalysis" component={YouTubeAnalysisScreen} />
        <Stack.Screen name="FoodResult" component={FoodResultScreen} />
        <Stack.Screen name="NutritionHistory" component={NutritionHistoryScreen} />
        <Stack.Screen name="MealLog" component={MealLogScreen} />
        <Stack.Screen name="Notifications" component={NotificationsScreen} />
        <Stack.Screen name="ChallengeCommunity" component={ChallengeCommunityScreen} />
      </Stack.Navigator>
    </NavigationContainer>
  );
};
