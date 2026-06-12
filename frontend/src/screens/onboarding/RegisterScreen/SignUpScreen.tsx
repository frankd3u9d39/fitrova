import { SafeAreaView } from 'react-native-safe-area-context';
import React, { useState } from 'react';
import { View, Text, StyleSheet,  KeyboardAvoidingView, Platform, ScrollView, TouchableOpacity, ActivityIndicator, Modal, TextInput } from 'react-native';
import { useNavigation, useRoute } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { RootStackParamList } from '../../../navigation/types';
import { Button } from '../../../components/buttons/Button';
import { Input } from '../../../components/inputs/Input';
import { HeaderLogo } from '../../../components/common/HeaderLogo';
import { Ionicons } from '@expo/vector-icons';
import { theme } from '../../../theme';
import { endpoints } from '../../../services/api/apiClient';
import { useEmailVerification } from '../../../hooks/useEmailVerification';
import { CustomAlert } from '../../../components/common/CustomAlert';
import AsyncStorage from '@react-native-async-storage/async-storage';


type NavigationProp = NativeStackNavigationProp<RootStackParamList, 'SignUp'>;

export const SignUpScreen = () => {
  const navigation = useNavigation<NavigationProp>();
  const [step, setStep] = React.useState(1);
  const [email, setEmail] = React.useState('');
  const [firstName, setFirstName] = React.useState('');
  const [lastName, setLastName] = React.useState('');
  const [password, setPassword] = React.useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const { verifyEmailAddress, isVerifying, verificationError, clearError } = useEmailVerification();





  const route = useRoute<any>();

  React.useEffect(() => {
    if (route.params?.verified && step === 1) {
      if (route.params?.email) {
        setEmail(route.params.email);
      }
      setStep(2);
    }
  }, [route.params?.verified, route.params?.email]);

  const handleContinue = async () => {
    if (step === 1) {
      if (!email) {
        CustomAlert.alert('Error', 'Please enter your email');
        return;
      }
      
      setIsLoading(true);
      try {
        const response = await fetch(endpoints.sendCode, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ email }),
        });
        
        const data = await response.json();
        
        if (response.ok && data.status === 'success') {
          navigation.navigate('EmailVerification', { email, firstName: '', signupFlow: true } as any);
        } else {
          CustomAlert.alert('Error', data.message || 'Failed to send verification code');
        }
      } catch (error) {
        CustomAlert.alert('Error', 'Could not connect to server. Is XAMPP running?');
      } finally {
        setIsLoading(false);
      }
    } else if (step === 2) {
      if (!firstName || !lastName) {
        CustomAlert.alert('Error', 'Please enter your full name');
        return;
      }
      setStep(3);
    } else if (step === 3) {
      if (!password || password !== confirmPassword) {
        CustomAlert.alert('Error', 'Passwords must match and cannot be empty');
        return;
      }
      
      setIsLoading(true);
      try {
        const requestBody = { email, firstName, lastName, password };
        
        const response = await fetch(endpoints.register, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(requestBody),
        });
        
        const data = await response.json();
        
        if (response.ok && data.status === 'success') {
          // Store session locally so they can auto-resume if they close the app mid-onboarding
          await AsyncStorage.setItem('user_session', JSON.stringify({
            id: data.user.id,
            firstName: firstName,
            surveyStep: 'Personalization',
            profile: null
          }));
          navigation.navigate('Personalization', { userId: data.user.id, firstName: firstName });
        } else {
          CustomAlert.alert('Registration Failed', data.message || 'Failed to create account');
        }
      } catch (error) {
        CustomAlert.alert('Network Error', 'Could not connect to the server. Please check your network.');
      } finally {
        setIsLoading(false);
      }
    }
  };

  const getTitle = () => {
    switch (step) {
      case 1:
        return "What's your email?";
      case 2:
        return "What's your name?";
      case 3:
        return "Create a password";
      default:
        return "Create Your Account";
    }
  };

  const getButtonTitle = () => {
    return step === 3 ? "Create Account" : "Continue";
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
        <View style={[styles.iconCircle, styles.iconCircle4]}>
          <Ionicons name="walk-outline" size={26} color="#000000ff" />
        </View>
        <View style={[styles.iconCircle, styles.iconCircle5]}>
          <Ionicons name="heart-outline" size={24} color="#000000ff" />
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
          {/* Progress Indicator */}
          <View style={styles.progressContainer}>
            <View style={[styles.progressDot, step >= 1 && styles.progressDotActive]} />
            <View style={[styles.progressDot, step >= 2 && styles.progressDotActive]} />
            <View style={[styles.progressDot, step >= 3 && styles.progressDotActive]} />
          </View>

          {/* Header */}
          <View style={styles.header}>
            <HeaderLogo />
          </View>
          
          <Text style={styles.title}>{getTitle()}</Text>

          {/* Form */}
          <View style={styles.formContainer}>
            {step === 1 && (
              <Input 
                label="Email"
                placeholder="name@example.com"
                keyboardType="email-address"
                autoCapitalize="none"
                value={email}
                onChangeText={setEmail}
              />
            )}

            {step === 2 && (
              <>
                <Input 
                  label="First Name"
                  placeholder="John"
                  autoCapitalize="words"
                  value={firstName}
                  onChangeText={setFirstName}
                />
                
                <Input 
                  label="Last Name"
                  placeholder="Doe"
                  autoCapitalize="words"
                  value={lastName}
                  onChangeText={setLastName}
                />
              </>
            )}

            {step === 3 && (
              <>
                <Input 
                  label="Password"
                  placeholder="Create a strong password"
                  isPassword
                  value={password}
                  onChangeText={setPassword}
                />

                <Input 
                  label="Confirm Password"
                  placeholder="Re-enter your password"
                  isPassword
                  value={confirmPassword}
                  onChangeText={setConfirmPassword}
                />
              </>
            )}

            <Button 
              title={
                (isLoading && step === 3) ? "Creating..." : 
                (isVerifying && step === 1) ? "Verifying..." : 
                (isLoading && step === 1) ? "Continue" :
                getButtonTitle()
              } 
              onPress={handleContinue} 
              style={styles.createButton}
              disabled={isLoading || isVerifying}
            />

            {step === 3 && (
              <Text style={styles.termsText}>
                By creating an account, you agree to our{' '}
                <Text style={styles.termsLink}>Terms of Service</Text> and{' '}
                <Text style={styles.termsLink}>Privacy Policy</Text>.
              </Text>
            )}
          </View>



          {/* Footer */}
          <View style={styles.footerContainer}>
            <Text style={styles.footerText}>Already have an account? </Text>
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
    top: '25%',
    left: '8%',
  },
  iconCircle3: {
    width: 75,
    height: 75,
    top: '50%',
    right: '5%',
  },
  iconCircle4: {
    width: 65,
    height: 65,
    top: '80%',
    left: '12%',
  },
  iconCircle5: {
    width: 60,
    height: 60,
    top: '85%',
    right: '15%',
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
  progressContainer: {
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: theme.spacing.lg,
    gap: 8,
  },
  progressDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: theme.colors.border,
  },
  progressDotActive: {
    backgroundColor: '#000000ff',
    width: 24,
  },
  header: {
    alignItems: 'center',
    marginBottom: theme.spacing.sm,
  },
  title: {
    marginTop:15,
    ...theme.typography.h2,
    textAlign: 'center',
    marginBottom: theme.spacing.xl,
  },
  formContainer: {
    marginBottom: theme.spacing.md,
  },
  createButton: {
    marginTop: theme.spacing.lg,
  },
  termsText: {
    ...theme.typography.bodySmall,
    color: theme.colors.textSecondary,
    textAlign: 'center',
    marginTop: theme.spacing.sm,
    lineHeight: 18,
  },
  termsLink: {
    color: theme.colors.primary,
    fontWeight: '600',
  },
  dividerContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: theme.spacing.md,
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
