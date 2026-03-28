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
  const [fullName, setFullName] = React.useState('');
  const [email, setEmail] = React.useState('');
  const [password, setPassword] = React.useState('');
  const [confirmPassword, setConfirmPassword] = React.useState('');

  return (
    <SafeAreaView style={styles.container}>
      <KeyboardAvoidingView 
        style={styles.keyboardView}
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
      >
        <ScrollView contentContainerStyle={styles.scrollContent} showsVerticalScrollIndicator={false}>
          {/* Header */}
          <View style={styles.header}>
            <HeaderLogo />
          </View>
          
          <Text style={styles.title}>Create Your Account</Text>

          {/* Form */}
          <View style={styles.formContainer}>
            <Input 
              label="Full Name"
              placeholder="John Doe"
              autoCapitalize="words"
              value={fullName}
              onChangeText={setFullName}
            />
            
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

            <Button 
              title="Create Account" 
              onPress={() => navigation.navigate('Personalization')} 
              style={styles.createButton}
            />

            <Text style={styles.termsText}>
              By creating an account, you agree to our{' '}
              <Text style={styles.termsLink}>Terms of Service</Text> and{' '}
              <Text style={styles.termsLink}>Privacy Policy</Text>.
            </Text>
          </View>

          {/* Divider */}
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
  keyboardView: {
    flex: 1,
  },
  scrollContent: {
    flexGrow: 1,
    paddingHorizontal: theme.spacing.lg,
    paddingTop: theme.spacing.lg,
    paddingBottom: theme.spacing.xxxl,
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
