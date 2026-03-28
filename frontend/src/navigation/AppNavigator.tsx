import React from 'react';
import { NavigationContainer } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { theme } from '../theme';

// Placeholder screen imports
import { WelcomeScreen } from '../screens/WelcomeScreen';
import { SignUpScreen } from '../screens/SignUpScreen';
import { PersonalizationScreen } from '../screens/PersonalizationScreen';
import { DashboardScreen } from '../screens/DashboardScreen';

export type RootStackParamList = {
  Welcome: undefined;
  SignUp: undefined;
  Personalization: undefined;
  Main: undefined;
};

export type MainTabParamList = {
  Home: undefined;
  Workout: undefined;
  Nutrition: undefined;
  Profile: undefined;
};

const Stack = createNativeStackNavigator<RootStackParamList>();
const Tab = createBottomTabNavigator<MainTabParamList>();

const MainTabs = () => {
  return (
    <Tab.Navigator
      screenOptions={{
        headerShown: false,
        tabBarActiveTintColor: theme.colors.primary,
        tabBarInactiveTintColor: theme.colors.textSecondary,
        tabBarStyle: {
          borderTopWidth: 0,
          elevation: 0,
          backgroundColor: '#fff',
          height: 60,
          paddingBottom: 10,
        },
      }}
    >
      <Tab.Screen name="Home" component={DashboardScreen} />
      {/* Placeholder screens for other tabs */}
      <Tab.Screen name="Workout" component={DashboardScreen} />
      <Tab.Screen name="Nutrition" component={DashboardScreen} />
      <Tab.Screen name="Profile" component={DashboardScreen} />
    </Tab.Navigator>
  );
};

export const AppNavigator = () => {
  return (
    <NavigationContainer>
      <Stack.Navigator
        screenOptions={{
          headerShown: false,
          contentStyle: { backgroundColor: theme.colors.background },
        }}
      >
        <Stack.Screen name="Welcome" component={WelcomeScreen} />
        <Stack.Screen name="SignUp" component={SignUpScreen} />
        <Stack.Screen name="Personalization" component={PersonalizationScreen} />
        <Stack.Screen name="Main" component={MainTabs} />
      </Stack.Navigator>
    </NavigationContainer>
  );
};
