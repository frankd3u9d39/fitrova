import { SafeAreaView } from 'react-native-safe-area-context';
import React from 'react';
import { View, StyleSheet, Image, Text,  Dimensions, ScrollView } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { RootStackParamList } from '../../../navigation/types';
import { Button } from '../../../components/buttons/Button';
import { HeaderLogo } from '../../../components/common/HeaderLogo';
import { Ionicons } from '@expo/vector-icons';
import { theme } from '../../../theme';

const { width } = Dimensions.get('window');

type NavigationProp = NativeStackNavigationProp<RootStackParamList, 'Welcome'>;

export const WelcomeScreen = () => {
  const navigation = useNavigation<NavigationProp>();

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView showsVerticalScrollIndicator={false}>
        {/* Header Logo */}
        <View style={styles.header}>
          <HeaderLogo />
        </View>

        {/* Image Card Container */}
        <View style={styles.imageCardContainer}>
          <View style={styles.imageCard}>
             <Image 
               source={require('../../../assets/images/welcome-avatar.png')}
               style={styles.avatarImage}
               resizeMode="cover"
             />
             
             {/* Mock Badges */}
             <View style={[styles.badge, styles.pulseBadge]}>
               <Ionicons name="heart-outline" size={14} color={theme.colors.primary} />
               <View>
                 <Text style={styles.badgeLabel}>PULSE</Text>
                 <Text style={styles.badgeValue}>128 BPM</Text>
               </View>
             </View>
             
             <View style={[styles.badge, styles.hydrationBadge]}>
               <Ionicons name="water" size={14} color={theme.colors.primary} />
               <View>
                 <Text style={styles.badgeLabel}>HYDRATION</Text>
                 <Text style={styles.badgeValue}>75%</Text>
               </View>
             </View>
          </View>
          
          {/* Subtle glow underneath the image card mock */}
          <View style={styles.glow} />
        </View>

        {/* Text Area */}
        <View style={styles.textArea}>
          <Text style={styles.title}>
            Your Intelligent{'\n'}
            <Text style={styles.titleHighlight}>Fitness</Text>{'\n'}
            Companion
          </Text>
          <Text style={styles.subtitle}>
            Personalized diet and workout plans powered by advanced AI algorithms.
          </Text>
        </View>

        {/* Buttons */}
        <View style={styles.footer}>
          <Button 
            title="Get Started" 
            onPress={() => navigation.navigate('SignUp')} 
          />
          <Button 
            title="I Already Have an Account" 
            variant="secondary"
            onPress={() => navigation.navigate('Login')} 
            style={{ marginTop: theme.spacing.md }}
          />
        </View>
      </ScrollView>
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.background,
    paddingHorizontal: theme.spacing.lg,
    height: theme.spacing.md,
    paddingTop: theme.spacing.md,
    paddingBottom: theme.spacing.md,
    justifyContent: 'space-between',
  },
  scrollContent: {
    flexGrow: 1,
    paddingHorizontal: theme.spacing.lg,
    paddingTop: theme.spacing.xl,
    paddingBottom: theme.spacing.xl,
    justifyContent: 'space-between',
  },
  header: {
    paddingTop: theme.spacing.xl,
    alignItems: 'center',
    marginBottom: theme.spacing.md,
  },
  imageCardContainer: {
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: theme.spacing.xl,
    position: 'relative',
  },
  imageCard: {
    width: width - theme.spacing.lg * 2,
    aspectRatio: 1,
    backgroundColor: '#1E2C26', // Dark green slate
    borderRadius: 40,
    overflow: 'hidden',
    position: 'relative',
    zIndex: 2,
  },
  avatarImage: {
    width: '100%',
    height: '100%',
  },
  glow: {
    position: 'absolute',
    bottom: -20,
    width: '80%',
    height: 40,
    backgroundColor: theme.colors.primary,
    opacity: 0.15,
    borderRadius: 100,
    transform: [{ scaleY: 0.5 }],
    zIndex: 1,
  },
  badge: {
    position: 'absolute',
    backgroundColor: 'rgba(255, 255, 255, 0.9)',
    borderRadius: theme.borderRadius.full,
    paddingHorizontal: theme.spacing.md,
    paddingVertical: theme.spacing.sm,
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.spacing.xs,
  },
  pulseBadge: {
    top: theme.spacing.xl,
    left: theme.spacing.md,
  },
  hydrationBadge: {
    bottom: theme.spacing.xl,
    right: theme.spacing.md,
  },
  badgeLabel: {
    fontSize: 8,
    fontWeight: '700',
    color: theme.colors.textSecondary,
    letterSpacing: 0.5,
  },
  badgeValue: {
    fontSize: 12,
    fontWeight: '800',
    color: theme.colors.text,
  },
  textArea: {
    alignItems: 'center',
    paddingHorizontal: theme.spacing.md,
    marginBottom: theme.spacing.xxxl,
  },
  title: {
    ...theme.typography.h1,
    textAlign: 'center',
    lineHeight: 34,
    marginBottom: theme.spacing.md,
  },
  titleHighlight: {
    color: theme.colors.primary,
  },
  subtitle: {
    ...theme.typography.body,
    color: theme.colors.textSecondary,
    textAlign: 'center',
    lineHeight: 24,
  },
  footer: {
    width: '100%',
  },
});
