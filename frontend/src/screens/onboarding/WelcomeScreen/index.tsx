import React from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  Dimensions,
  StatusBar,
} from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { SafeAreaView } from 'react-native-safe-area-context';
import { colors, typography, spacing, theme } from '../../../theme';
import WelcomeIllustration from './WelcomeIllustration';
import AppLogo from './AppLogo';

const { width, height } = Dimensions.get('window');

interface WelcomeScreenProps {
  navigation: any;
}

const WelcomeScreen: React.FC<WelcomeScreenProps> = ({ navigation }) => {
  const handleGetStarted = () => {
    navigation.navigate('Register');
  };

  const handleLogin = () => {
    navigation.navigate('Login');
  };

  return (
    <SafeAreaView style={styles.container} edges={['top']}>
      <StatusBar barStyle="dark-content" backgroundColor={colors.white} />

      <LinearGradient
        colors={[colors.gradientStart, colors.gradientEnd]}
        style={styles.gradient}
      >
        {/* Top Section - Logo and Branding */}
        <View style={styles.topSection}>
          <AppLogo />
          <Text style={styles.appName}>AI FitTracker</Text>
          <Text style={styles.tagline}>
            Your Personal AI Coach For Smarter Fitness and{'\n'}nutrition companion
          </Text>
        </View>

        {/* Middle Section - Illustration */}
        <View style={styles.middleSection}>
          <WelcomeIllustration />
        </View>

        {/* Bottom Section - CTA Buttons */}
        <View style={styles.bottomSection}>
          <TouchableOpacity
            style={styles.primaryButton}
            onPress={handleGetStarted}
            activeOpacity={0.8}
          >
            <Text style={styles.primaryButtonText}>Get Started</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.secondaryButton}
            onPress={handleLogin}
            activeOpacity={0.8}
          >
            <Text style={styles.secondaryButtonText}>Login</Text>
          </TouchableOpacity>

          {/* Optional: Feature highlights */}
          <View style={styles.featuresContainer}>
            <FeatureTag icon="💪" text="Track Workouts" />
            <FeatureTag icon="🥗" text="Monitor Diet" />
            <FeatureTag icon="🤖" text="AI Recognition" />
          </View>
        </View>
      </LinearGradient>
    </SafeAreaView>
  );
};

// Feature Tag Component
const FeatureTag: React.FC<{ icon: string; text: string }> = ({ icon, text }) => (
  <View style={styles.featureTag}>
    <Text style={styles.featureIcon}>{icon}</Text>
    <Text style={styles.featureText}>{text}</Text>
  </View>
);

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.white,
  },
  gradient: {
    flex: 1,
  },
  topSection: {
    alignItems: 'center',
    paddingTop: spacing['2xl'],
    paddingHorizontal: spacing.lg,
  },
  appName: {
    fontSize: typography.fontSize['4xl'],
    fontWeight: typography.fontWeight.bold,
    color: colors.gray[900],
    marginTop: spacing.md,
    letterSpacing: -0.5,
  },
  tagline: {
    fontSize: typography.fontSize.base,
    fontWeight: typography.fontWeight.regular,
    color: colors.gray[600],
    textAlign: 'center',
    marginTop: spacing.sm,
    lineHeight: typography.fontSize.base * typography.lineHeight.relaxed,
  },
  middleSection: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: spacing.lg,
  },
  bottomSection: {
    paddingHorizontal: spacing.lg,
    paddingBottom: spacing['2xl'],
  },
  primaryButton: {
    backgroundColor: colors.primary,
    paddingVertical: spacing.md + 2,
    borderRadius: theme.borderRadius.lg,
    alignItems: 'center',
    marginBottom: spacing.md,
    ...theme.shadows.md,
  },
  primaryButtonText: {
    color: colors.white,
    fontSize: typography.fontSize.lg,
    fontWeight: typography.fontWeight.semibold,
  },
  secondaryButton: {
    backgroundColor: 'transparent',
    paddingVertical: spacing.md + 2,
    borderRadius: theme.borderRadius.lg,
    alignItems: 'center',
    borderWidth: 2,
    borderColor: colors.primary,
  },
  secondaryButtonText: {
    color: colors.primary,
    fontSize: typography.fontSize.lg,
    fontWeight: typography.fontWeight.semibold,
  },
  featuresContainer: {
    flexDirection: 'row',
    justifyContent: 'center',
    marginTop: spacing.xl,
    gap: spacing.md,
  },
  featureTag: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.white,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    borderRadius: theme.borderRadius.full,
    ...theme.shadows.sm,
  },
  featureIcon: {
    fontSize: typography.fontSize.base,
    marginRight: spacing.xs,
  },
  featureText: {
    fontSize: typography.fontSize.xs,
    fontWeight: typography.fontWeight.medium,
    color: colors.gray[700],
  },
});

export default WelcomeScreen;
