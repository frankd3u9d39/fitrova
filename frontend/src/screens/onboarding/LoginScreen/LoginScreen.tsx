import { SafeAreaView } from 'react-native-safe-area-context';
import { CustomAlert } from '../../../components/common/CustomAlert';
import React, { useState } from 'react';
import { View, Text, StyleSheet,  KeyboardAvoidingView, Platform, ScrollView, TouchableOpacity, Alert, Modal, TextInput, ActivityIndicator } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { RootStackParamList } from '../../../navigation/types';
import { Button } from '../../../components/buttons/Button';
import { Input } from '../../../components/inputs/Input';
import { HeaderLogo } from '../../../components/common/HeaderLogo';
import { Ionicons } from '@expo/vector-icons';
import { theme } from '../../../theme';
import { endpoints } from '../../../services/api/apiClient';
import AsyncStorage from '@react-native-async-storage/async-storage';


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
        
        // Save session details to AsyncStorage for persistent auto-login
        await AsyncStorage.setItem('user_session', JSON.stringify({
          id,
          firstName,
          surveyStep,
          profile
        }));
        
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
        } else if (surveyStep === 'SubscriptionSelection') {
           navigation.navigate('SubscriptionSelection', { userId: id, firstName });
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
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
        keyboardVerticalOffset={Platform.select({ ios: 64, android: 40 })}
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

            <TouchableOpacity 
              style={styles.forgotPassword}
              onPress={() => navigation.navigate('ForgotPassword')}
            >
              <Text style={styles.forgotPasswordText}>Forgot Password?</Text>
            </TouchableOpacity>

            <Button 
              title={isLoading ? "Logging in..." : "Log in"} 
              onPress={handleLogin} 
              style={styles.loginButton}
              disabled={isLoading}
            />
          </View>



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
  googleModalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'flex-end',
  },
  googleModalBackdropClose: {
    flex: 1,
  },
  googleBottomSheet: {
    backgroundColor: '#FFFFFF',
    borderTopLeftRadius: 24,
    borderTopRightRadius: 24,
    paddingHorizontal: 24,
    paddingTop: 12,
    paddingBottom: Platform.OS === 'ios' ? 44 : 24,
    maxHeight: '85%',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: -4 },
    shadowOpacity: 0.1,
    shadowRadius: 12,
    elevation: 20,
    position: 'relative',
  },
  googleDragHandle: {
    width: 38,
    height: 4,
    borderRadius: 2,
    backgroundColor: '#E5E7EB',
    alignSelf: 'center',
    marginBottom: 20,
  },
  googleHeader: {
    alignItems: 'center',
    marginBottom: 20,
  },
  googleBrandRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  googleTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: '#1F2937',
  },
  googleSubtitle: {
    fontSize: 13,
    color: '#6B7280',
    textAlign: 'center',
    marginTop: 4,
  },
  googleAccountItem: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 14,
    borderBottomWidth: 1,
    borderBottomColor: '#F3F4F6',
    gap: 14,
  },
  googleAvatar: {
    width: 40,
    height: 40,
    borderRadius: 20,
    justifyContent: 'center',
    alignItems: 'center',
  },
  googleAvatarText: {
    fontSize: 16,
    fontWeight: '600',
    color: '#FFFFFF',
  },
  googleAccountInfo: {
    flex: 1,
  },
  googleAccountName: {
    fontSize: 14,
    fontWeight: '600',
    color: '#1F2937',
  },
  googleAccountEmail: {
    fontSize: 12,
    color: '#6B7280',
    marginTop: 2,
  },
  googleCustomForm: {
    gap: 12,
    paddingVertical: 10,
  },
  googleInput: {
    borderWidth: 1,
    borderColor: '#D1D5DB',
    borderRadius: 8,
    paddingHorizontal: 14,
    paddingVertical: 10,
    fontSize: 14,
    color: '#1F2937',
    backgroundColor: '#F9FAFB',
  },
  googleCustomActions: {
    flexDirection: 'row',
    gap: 12,
    marginTop: 10,
  },
  googleButton: {
    flex: 1,
    height: 44,
    borderRadius: 8,
    justifyContent: 'center',
    alignItems: 'center',
  },
  googleButtonPrimary: {
    backgroundColor: '#10B981',
  },
  googleButtonSecondary: {
    borderWidth: 1,
    borderColor: '#D1D5DB',
    backgroundColor: '#FFFFFF',
  },
  googleButtonText: {
    fontSize: 14,
    fontWeight: '600',
  },
  googleLoaderOverlay: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    backgroundColor: 'rgba(255, 255, 255, 0.9)',
    justifyContent: 'center',
    alignItems: 'center',
    borderTopLeftRadius: 24,
    borderTopRightRadius: 24,
    zIndex: 10,
  },
  googleLoaderText: {
    fontSize: 14,
    color: '#4B5563',
    fontWeight: '600',
    marginTop: 12,
  },
});
