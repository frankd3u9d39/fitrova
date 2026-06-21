import React, { useEffect, useRef } from 'react';
import { StyleSheet, Text, Image, Animated, Easing, View, Dimensions } from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import * as SplashScreen from 'expo-splash-screen';
import { theme } from '../../../theme';

const { width } = Dimensions.get('window');

interface AnimatedSplashScreenProps {
  onAnimationComplete: () => void;
}

export const AnimatedSplashScreen: React.FC<AnimatedSplashScreenProps> = ({ onAnimationComplete }) => {
  // Animation values
  const fadeAnimLogo = useRef(new Animated.Value(0)).current;
  const scaleAnimLogo = useRef(new Animated.Value(0.7)).current;
  const fadeAnimMotto = useRef(new Animated.Value(0)).current;
  const translateAnimMotto = useRef(new Animated.Value(20)).current;
  const fadeAnimScreen = useRef(new Animated.Value(1)).current;

  useEffect(() => {
    // Hide the native splash screen immediately when this component is ready
    const hideNativeSplash = async () => {
      try {
        await SplashScreen.hideAsync();
      } catch (error) {
        console.warn('Could not hide native splash screen:', error);
      }
    };
    
    hideNativeSplash();

    // Kick off the animation sequence
    Animated.sequence([
      // Step 1: Scale and fade in the logo
      Animated.parallel([
        Animated.timing(fadeAnimLogo, {
          toValue: 1,
          duration: 900,
          useNativeDriver: true,
        }),
        Animated.timing(scaleAnimLogo, {
          toValue: 1,
          duration: 1100,
          easing: Easing.out(Easing.back(1.3)),
          useNativeDriver: true,
        }),
      ]),
      
      // Step 2: Small gap, then fade and slide up the motto text
      Animated.delay(150),
      Animated.parallel([
        Animated.timing(fadeAnimMotto, {
          toValue: 1,
          duration: 800,
          useNativeDriver: true,
        }),
        Animated.timing(translateAnimMotto, {
          toValue: 0,
          duration: 800,
          easing: Easing.out(Easing.quad),
          useNativeDriver: true,
        }),
      ]),
      
      // Step 3: Hold the completed visual for a brief moment
      Animated.delay(1200),
      
      // Step 4: Smoothly fade out the entire screen
      Animated.timing(fadeAnimScreen, {
        toValue: 0,
        duration: 400,
        useNativeDriver: true,
      }),
    ]).start(() => {
      onAnimationComplete();
    });
  }, []);

  return (
    <Animated.View style={[styles.container, { opacity: fadeAnimScreen }]}>
      <LinearGradient
        colors={[theme.colors.white, '#F0F4F2']} // Extremely subtle clean light green-gray gradient
        style={StyleSheet.absoluteFill}
        start={{ x: 0, y: 0 }}
        end={{ x: 1, y: 1 }}
      />
      
      <View style={styles.contentContainer}>
        {/* Logo Container */}
        <Animated.View 
          style={[
            styles.logoContainer, 
            { 
              opacity: fadeAnimLogo, 
              transform: [{ scale: scaleAnimLogo }] 
            }
          ]}
        >
          <Image
            source={require('../../../../assets/Logo.png')}
            style={styles.logo}
            resizeMode="contain"
          />
        </Animated.View>

        {/* Motto / Tagline Container */}
        <Animated.View 
          style={[
            styles.mottoContainer, 
            { 
              opacity: fadeAnimMotto, 
              transform: [{ translateY: translateAnimMotto }] 
            }
          ]}
        >
          <Text style={styles.mottoText}>Your Intelligent Path to Fitness</Text>
          <View style={styles.accentLine} />
        </Animated.View>
      </View>
    </Animated.View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  contentContainer: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: theme.spacing.xl,
  },
  logoContainer: {
    alignItems: 'center',
    justifyContent: 'center',
    width: width * 0.5,
    aspectRatio: 1,
    marginBottom: theme.spacing.xl,
  },
  logo: {
    width: '100%',
    height: '100%',
  },
  mottoContainer: {
    alignItems: 'center',
    marginTop: theme.spacing.md,
  },
  mottoText: {
    fontSize: 18,
    fontWeight: '600',
    color: theme.colors.text,
    textAlign: 'center',
    letterSpacing: 0.5,
    lineHeight: 24,
  },
  accentLine: {
    marginTop: theme.spacing.md,
    width: 40,
    height: 3,
    backgroundColor: theme.colors.primary, // Fitrova emerald green
    borderRadius: theme.borderRadius.full,
  },
});
