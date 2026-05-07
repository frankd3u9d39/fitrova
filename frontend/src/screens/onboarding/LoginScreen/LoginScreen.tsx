import { SafeAreaView } from 'react-native-safe-area-context';
import { CustomAlert } from '../../../components/common/CustomAlert';
import React, { useState } from 'react';
import { View, Text, StyleSheet,  KeyboardAvoidingView, Platform, ScrollView, TouchableOpacity, Alert } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { RootStackParamList } from '../../../navigation/types';
import { Button } from '../../../components/buttons/Button';
import { Input } from '../../../components/inputs/Input';
import { HeaderLogo } from '../../../components/common/HeaderLogo';
import { Ionicons } from '@expo/vector-icons';
import { theme } from '../../../theme';
import { endpoints } from '../../../services/api/apiClient';

type NavigationProp = NativeStackNavigationProp<RootStackParamList, 'Login'>;

export const LoginScreen = () => {
  const navigation = useNavigation<NavigationProp>();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [isLoading, setIsLoading] = useState(false);

  const handleLogin = async () => {
    if (!email || !password) {
      CustomAlert.alert('Error', 'Please enter your email and password');
      return;
    }

    setIsLoading(true);
    try {
      const response = await fetch(endpoints.login, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ email, password }),
      });

      const data = await response.json();

      if (response.ok && data.status === 'success') {
        const { id, firstName, surveyStep, profile } = data.user;
        
        if (surveyStep === 'Complete') {
          navigation.navigate('Main', { firstName, userId: id });
        } else if (surveyStep === 'Personalization') {
          navigation.navigate('Personalization', { userId: id, firstName });
        } else if (surveyStep === 'GoalSetting') {
          navigation.navigate('GoalSetting', { 
            userId: id,
            firstName,
            age: profile.age,
            gender: profile.gender,
            height: profile.height,
            weight: profile.weight,
            activityLevel: profile.activityLevel,
            goal: profile.goal
          });
        } else if (surveyStep === 'Restrictions') {
           navigation.navigate('Restrictions', { 
            userId: id,
            firstName,
            age: profile.age,
            gender: profile.gender,
            height: profile.height,
            weight: profile.weight,
            activityLevel: profile.activityLevel,
            goal: profile.goal,
            selectedGoal: profile.selectedGoal,
            targetWeight: profile.targetWeight,
            targetDate: profile.targetDate
          });
        }
      } else {
        CustomAlert.alert('Login Failed', data.message || 'Invalid credentials');
      }
    } catch (error) {
      CustomAlert.alert('Error', 'Could not connect to the server. Please check your network.');
      console.error(error);
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <SafeAreaView style={styles.container}>
      {/* Decorative Background Icons */}
      <View style={styles.decorativeIconsContainer}>
        <View style={[styles.iconCircle, styles.iconCircle1]}>
          <Ionicons name="barbell-outline" size={32} color="#000000ff"/>
        </View>
        <View style={[styles.iconCircle, styles.iconCircle2]}>
          <Ionicons name="fitness-outline" size={28} color="#000000ff" />
        </View>
        <View style={[styles.iconCircle, styles.iconCircle3]}>
          <Ionicons name="bicycle-outline" size={30} color="#000000ff" />
        </View>
      </View>

      <KeyboardAvoidingView 
        style={styles.keyboardView}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      >
        <ScrollView 
          contentContainerStyle={styles.scrollContent} 
          showsVerticalScrollIndicator={false}
          keyboardShouldPersistTaps="handled"
        >
          {/* Header */}
          <View style={styles.header}>
            <HeaderLogo />
          </View>
          
          <Text style={styles.title}>Welcome Back</Text>

          {/* Form */}
          <View style={styles.formContainer}>
            <Input 
              label="Email"
              placeholder="name@example.com"
              keyboardType="email-address"
              autoCapitalize="none"
              value={email}
              onChangeText={setEmail}
            />

            <Input 
              label="Password"
              placeholder="Enter your password"
              isPassword
              value={password}
              onChangeText={setPassword}
            />

            <TouchableOpacity style={styles.forgotPassword}>
              <Text style={styles.forgotPasswordText}>Forgot Password?</Text>
            </TouchableOpacity>

            <Button 
              title={isLoading ? "Logging in..." : "Log in"} 
              onPress={handleLogin} 
              style={styles.loginButton}
              disabled={isLoading}
            />
          </View>

          <View style={styles.dividerContainer}>
            <View style={styles.dividerItem} />
            <Text style={styles.dividerText}>or continue with</Text>
            <View style={styles.dividerItem} />
          </View>

          {/* Social */}
          <Button 
            title="Sign in with Google" 
            variant="outline"
            onPress={() => {}} 
            style={styles.socialButton}
            icon={<Ionicons name="logo-google" size={20} color="#DB4437" />}
          />

          {/* Footer */}
          <View style={styles.footerContainer}>
            <Text style={styles.footerText}>Don't have an account? </Text>
            <TouchableOpacity onPress={() => navigation.navigate('SignUp')}>
              <Text style={styles.footerLink}>Sign up</Text>
            </TouchableOpacity>
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  decorativeIconsContainer: {
    position: 'absolute',
    width: '100%',
    height: '100%',
    zIndex: 0,
  },
  iconCircle: {
    position: 'absolute',
    backgroundColor: '#FFFFFF',
    borderRadius: 50,
    justifyContent: 'center',
    alignItems: 'center',
    opacity: 0.14,
  },
  iconCircle1: {
    width: 80,
    height: 80,
    top: '10%',
    right: '10%',
  },
  iconCircle2: {
    width: 70,
    height: 70,
    top: '45%',
    left: '8%',
  },
  iconCircle3: {
    width: 75,
    height: 75,
    top: '70%',
    right: '5%',
  },
  keyboardView: {
    flex: 1,
    zIndex: 1,
  },
  scrollContent: {
    paddingHorizontal: theme.spacing.lg,
    paddingTop: theme.spacing.xxxl,
    paddingBottom: theme.spacing.xxxl,
  },
  header: {
    alignItems: 'center',
    marginBottom: theme.spacing.sm,
  },
  title: {
    marginTop: 15,
    ...theme.typography.h2,
    textAlign: 'center',
    marginBottom: theme.spacing.xl,
  },
  formContainer: {
    marginBottom: theme.spacing.md,
  },
  forgotPassword: {
    alignSelf: 'flex-end',
    marginBottom: theme.spacing.lg,
  },
  forgotPasswordText: {
    ...theme.typography.bodySmall,
    color: theme.colors.primary,
    fontWeight: '600',
  },
  loginButton: {
    marginTop: theme.spacing.xs,
  },
  dividerContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: theme.spacing.md,
    marginTop: theme.spacing.lg,
  },
  dividerItem: {
    flex: 1,
    height: 1,
    backgroundColor: theme.colors.border,
  },
  dividerText: {
    ...theme.typography.bodySmall,
    color: theme.colors.textSecondary,
    paddingHorizontal: theme.spacing.md,
  },
  socialButton: {
    marginBottom: 'auto',
  },
  footerContainer: {
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
    marginTop: theme.spacing.xxl,
  },
  footerText: {
    ...theme.typography.bodySmall,
    color: theme.colors.textSecondary,
  },
  footerLink: {
    ...theme.typography.bodySmall,
    color: theme.colors.primary,
    fontWeight: '700',
  },
});
