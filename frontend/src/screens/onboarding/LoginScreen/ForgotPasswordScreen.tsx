import React, { useState } from 'react';
import { 
  View, 
  Text, 
  StyleSheet, 
  KeyboardAvoidingView, 
  Platform, 
  ScrollView, 
  TouchableOpacity 
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useNavigation, useRoute, RouteProp } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { RootStackParamList } from '../../../navigation/types';
import { Button } from '../../../components/buttons/Button';
import { Input } from '../../../components/inputs/Input';
import { HeaderLogo } from '../../../components/common/HeaderLogo';
import { Ionicons } from '@expo/vector-icons';
import { theme } from '../../../theme';
import { endpoints } from '../../../services/api/apiClient';
import { CustomAlert } from '../../../components/common/CustomAlert';

type NavigationProp = NativeStackNavigationProp<RootStackParamList, 'ForgotPassword'>;
type ForgotPasswordRouteProp = RouteProp<RootStackParamList, 'ForgotPassword'>;

export const ForgotPasswordScreen = () => {
  const navigation = useNavigation<NavigationProp>();
  const route = useRoute<ForgotPasswordRouteProp>();
  
  const [email, setEmail] = useState(route.params?.email || '');
  const [code, setCode] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [step, setStep] = useState<1 | 2>(1);
  const [isLoading, setIsLoading] = useState(false);

  const handleSendCode = async () => {
    if (!email.trim()) {
      CustomAlert.alert('Error', 'Please enter your email address');
      return;
    }

    setIsLoading(true);
    try {
      const response = await fetch(endpoints.forgotPassword, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          action: 'send_code',
          email: email.trim()
        }),
      });

      const data = await response.json();

      if (response.ok && data.status === 'success') {
        CustomAlert.alert('Success', data.message);
        setStep(2);
      } else {
        CustomAlert.alert('Error', data.message || 'Failed to send reset code');
      }
    } catch (error) {
      CustomAlert.alert('Error', 'Could not connect to the server. Please check your network.');
      console.error(error);
    } finally {
      setIsLoading(false);
    }
  };

  const handleResetPassword = async () => {
    if (!code.trim() || !newPassword || !confirmPassword) {
      CustomAlert.alert('Error', 'Please fill in all verification and password fields');
      return;
    }

    if (newPassword !== confirmPassword) {
      CustomAlert.alert('Error', 'New passwords do not match');
      return;
    }

    if (newPassword.length < 8) {
      CustomAlert.alert('Error', 'Password must be at least 8 characters long');
      return;
    }

    setIsLoading(true);
    try {
      const response = await fetch(endpoints.forgotPassword, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          action: 'verify_and_reset',
          email: email.trim(),
          code: code.trim(),
          new_password: newPassword
        }),
      });

      const data = await response.json();

      if (response.ok && data.status === 'success') {
        CustomAlert.alert('Password Reset Successful', data.message);
        navigation.navigate('Login');
      } else {
        CustomAlert.alert('Error', data.message || 'Verification and password reset failed');
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
          <Ionicons name="key-outline" size={32} color="#000" />
        </View>
        <View style={[styles.iconCircle, styles.iconCircle2]}>
          <Ionicons name="shield-checkmark-outline" size={28} color="#000" />
        </View>
      </View>

      {/* Header Back Button */}
      <View style={styles.navHeader}>
        <TouchableOpacity 
          style={styles.backButton} 
          onPress={() => step === 2 ? setStep(1) : navigation.goBack()}
        >
          <Ionicons name="arrow-back" size={24} color={theme.colors.text} />
        </TouchableOpacity>
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
          {/* Brand Header */}
          <View style={styles.header}>
            <HeaderLogo />
          </View>
          
          <Text style={styles.title}>
            {step === 1 ? 'Reset Password' : 'New Password'}
          </Text>
          
          <Text style={styles.subtitle}>
            {step === 1 
              ? "Enter your email address and we'll send you a 6-digit verification code to reset your password."
              : "Enter the 6-digit verification code sent to your email, along with your new secure password."
            }
          </Text>

          {/* Form Wizard */}
          {step === 1 ? (
            <View style={styles.formContainer}>
              <Input 
                label="Email Address"
                placeholder="name@example.com"
                keyboardType="email-address"
                autoCapitalize="none"
                value={email}
                onChangeText={setEmail}
              />

              <Button 
                title={isLoading ? "Sending..." : "Send Reset Code"} 
                onPress={handleSendCode} 
                style={styles.actionButton}
                disabled={isLoading}
              />
            </View>
          ) : (
            <View style={styles.formContainer}>
              <Input 
                label="Verification Code"
                placeholder="Enter 6-digit code"
                keyboardType="number-pad"
                maxLength={6}
                value={code}
                onChangeText={setCode}
              />

              <Input 
                label="New Password"
                placeholder="At least 8 characters"
                isPassword
                value={newPassword}
                onChangeText={setNewPassword}
              />

              <Input 
                label="Confirm New Password"
                placeholder="Repeat new password"
                isPassword
                value={confirmPassword}
                onChangeText={setConfirmPassword}
              />

              <Button 
                title={isLoading ? "Resetting..." : "Reset Password"} 
                onPress={handleResetPassword} 
                style={styles.actionButton}
                disabled={isLoading}
              />
            </View>
          )}

          {/* Footer Navigation */}
          <View style={styles.footerContainer}>
            <Text style={styles.footerText}>Back to </Text>
            <TouchableOpacity onPress={() => navigation.navigate('Login')}>
              <Text style={styles.footerLink}>Log in</Text>
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
    opacity: 0.12,
  },
  iconCircle1: {
    width: 80,
    height: 80,
    top: '12%',
    right: '15%',
  },
  iconCircle2: {
    width: 70,
    height: 70,
    top: '55%',
    left: '8%',
  },
  navHeader: {
    paddingHorizontal: 20,
    paddingTop: 10,
    zIndex: 2,
  },
  backButton: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: '#FFFFFF',
    justifyContent: 'center',
    alignItems: 'center',
    ...theme.shadows.sm,
  },
  keyboardView: {
    flex: 1,
    zIndex: 1,
  },
  scrollContent: {
    paddingHorizontal: theme.spacing.lg,
    paddingTop: theme.spacing.xl,
    paddingBottom: theme.spacing.xxxl,
  },
  header: {
    alignItems: 'center',
    marginBottom: theme.spacing.xs,
  },
  title: {
    marginTop: 15,
    ...theme.typography.h2,
    textAlign: 'center',
    marginBottom: 10,
  },
  subtitle: {
    ...theme.typography.bodySmall,
    color: theme.colors.textSecondary,
    textAlign: 'center',
    lineHeight: 18,
    paddingHorizontal: 15,
    marginBottom: theme.spacing.xl,
  },
  formContainer: {
    marginBottom: theme.spacing.md,
  },
  actionButton: {
    marginTop: theme.spacing.sm,
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
