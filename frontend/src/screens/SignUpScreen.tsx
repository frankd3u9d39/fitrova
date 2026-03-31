import React from 'react';
import { View, Text, StyleSheet, SafeAreaView, KeyboardAvoidingView, Platform, ScrollView, TouchableOpacity } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { RootStackParamList } from '../navigation/AppNavigator';
import { Button } from '../components/buttons/Button';
import { Input } from '../components/inputs/Input';
import { HeaderLogo } from '../components/common/HeaderLogo';
import { Ionicons } from '@expo/vector-icons';
import { theme } from '../theme';

type NavigationProp = NativeStackNavigationProp<RootStackParamList, 'SignUp'>;

export const SignUpScreen = () => {
  const navigation = useNavigation<NavigationProp>();
  const [step, setStep] = React.useState(1);
  const [email, setEmail] = React.useState('');
  const [firstName, setFirstName] = React.useState('');
  const [lastName, setLastName] = React.useState('');
  const [password, setPassword] = React.useState('');
  const [confirmPassword, setConfirmPassword] = React.useState('');

  const handleContinue = () => {
    if (step === 1) {
      setStep(2);
    } else if (step === 2) {
      setStep(3);
    } else if (step === 3) {
      navigation.navigate('Personalization');
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
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        keyboardVerticalOffset={Platform.OS === 'ios' ? 0 : 0}
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
              title={getButtonTitle()} 
              onPress={handleContinue} 
              style={styles.createButton}
            />

            {step === 3 && (
              <Text style={styles.termsText}>
                By creating an account, you agree to our{' '}
                <Text style={styles.termsLink}>Terms of Service</Text> and{' '}
                <Text style={styles.termsLink}>Privacy Policy</Text>.
              </Text>
            )}
          </View>

          {/* Divider - Only show on step 1 */}
          {step === 1 && (
            <>
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
            </>
          )}

          {/* Footer */}
          <View style={styles.footerContainer}>
            <Text style={styles.footerText}>Already have an account? </Text>
            <TouchableOpacity>
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
});
