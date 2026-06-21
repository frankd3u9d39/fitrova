import React, { useEffect, useState, useRef } from 'react';
import { StyleSheet, View, Text, FlatList, TouchableOpacity, Dimensions, ActivityIndicator } from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { Ionicons } from '@expo/vector-icons';
import { theme } from '../../theme';
import { getOnboardingSlides, completeOnboarding, OnboardingSlideData } from '../../services/api/systemService';

const { width, height } = Dimensions.get('window');

interface OnboardingCarouselProps {
  userId?: number;
  onComplete: () => void;
}

// Fallback slides in case of network/database failure
const FALLBACK_SLIDES: OnboardingSlideData[] = [
  {
    id: 1,
    title: 'Welcome to Fitrova',
    description: 'Your AI fitness companion to help you reach your goals.',
    sort_order: 1,
  },
  {
    id: 2,
    title: 'AI Workout Assistant',
    description: 'Get smarter, customized workout guidance based on your personal metrics.',
    sort_order: 2,
  },
  {
    id: 3,
    title: 'Track Your Progress',
    description: 'Monitor your improvements, log your nutrition, and stay consistent.',
    sort_order: 3,
  },
];

export const OnboardingCarousel: React.FC<OnboardingCarouselProps> = ({ userId, onComplete }) => {
  const [slides, setSlides] = useState<OnboardingSlideData[]>([]);
  const [loading, setLoading] = useState(true);
  const [currentIndex, setCurrentIndex] = useState(0);
  const flatListRef = useRef<FlatList>(null);
  const timerRef = useRef<NodeJS.Timeout | null>(null);

  useEffect(() => {
    const fetchSlides = async () => {
      try {
        const fetchedSlides = await getOnboardingSlides();
        if (fetchedSlides && fetchedSlides.length > 0) {
          setSlides(fetchedSlides);
        } else {
          setSlides(FALLBACK_SLIDES);
        }
      } catch (error) {
        console.warn('⚠️ Failed to load onboarding slides from server, using fallbacks:', error);
        setSlides(FALLBACK_SLIDES);
      } finally {
        setLoading(false);
      }
    };

    fetchSlides();
  }, []);

  // Auto-scroll logic
  useEffect(() => {
    if (slides.length === 0) return;

    const timer = setTimeout(() => {
      const nextIndex = currentIndex + 1;
      if (nextIndex < slides.length) {
        flatListRef.current?.scrollToIndex({
          index: nextIndex,
          animated: true,
        });
        setCurrentIndex(nextIndex);
      } else {
        // Reached last slide: automatically complete onboarding after 3.5 seconds
        handleFinish();
      }
    }, 3500);

    return () => {
      clearTimeout(timer);
    };
  }, [slides, currentIndex]);

  const handleScroll = (event: any) => {
    const contentOffsetX = event.nativeEvent.contentOffset.x;
    const index = Math.round(contentOffsetX / width);
    setCurrentIndex(index);
  };

  const handleFinish = async () => {
    try {
      // 1. Save local seen state
      await AsyncStorage.setItem('has_seen_onboarding', 'true');
      
      // 2. Sync to server in background if logged in
      if (userId) {
        completeOnboarding(userId);
      }
    } catch (error) {
      console.warn('Error saving onboarding completion:', error);
    } finally {
      // 3. Trigger callback to update parent state
      onComplete();
    }
  };

  // Maps slide titles to beautiful icons
  const getIconName = (title: string): keyof typeof Ionicons.glyphMap => {
    const lowerTitle = title.toLowerCase();
    if (lowerTitle.includes('welcome')) return 'sparkles-outline';
    if (lowerTitle.includes('workout') || lowerTitle.includes('assistant')) return 'barbell-outline';
    if (lowerTitle.includes('track') || lowerTitle.includes('progress') || lowerTitle.includes('nutrition')) return 'analytics-outline';
    return 'fitness-outline';
  };

  const renderSlide = ({ item }: { item: OnboardingSlideData }) => {
    const iconName = getIconName(item.title);
    return (
      <View style={styles.slideContainer}>
        {/* Animated Glow Circle Background */}
        <View style={styles.iconCircle}>
          <Ionicons name={iconName} size={64} color={theme.colors.primary} />
          <View style={styles.pulseRing} />
        </View>
        
        <Text style={styles.title}>{item.title}</Text>
        <Text style={styles.description}>{item.description}</Text>
      </View>
    );
  };

  if (loading) {
    return (
      <View style={styles.loaderContainer}>
        <ActivityIndicator size="large" color={theme.colors.primary} />
      </View>
    );
  }

  return (
    <View style={styles.container}>
      {/* Slide List */}
      <FlatList
        ref={flatListRef}
        data={slides}
        renderItem={renderSlide}
        keyExtractor={(item) => item.id.toString()}
        horizontal
        pagingEnabled
        showsHorizontalScrollIndicator={false}
        onScroll={handleScroll}
        scrollEventThrottle={16}
        style={styles.flatList}
        getItemLayout={(data, index) => (
          { length: width, offset: width * index, index }
        )}
      />

      {/* Footer Controls */}
      <View style={styles.footer}>
        {/* Pagination Dots */}
        <View style={styles.dotsContainer}>
          {slides.map((_, index) => (
            <View
              key={index}
              style={[
                styles.dot,
                currentIndex === index ? styles.activeDot : null,
              ]}
            />
          ))}
        </View>

        {/* Buttons Row */}
        <View style={styles.buttonRow}>
          {currentIndex === slides.length - 1 && (
            <TouchableOpacity 
              style={styles.getStartedButton} 
              onPress={handleFinish} 
              activeOpacity={0.8}
            >
              <Text style={styles.getStartedButtonText}>GET STARTED</Text>
            </TouchableOpacity>
          )}
        </View>
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.background, // Pure white theme background
  },
  loaderContainer: {
    flex: 1,
    backgroundColor: theme.colors.background,
    justifyContent: 'center',
    alignItems: 'center',
  },
  flatList: {
    flex: 1,
  },
  slideContainer: {
    width: width,
    paddingHorizontal: theme.spacing.xl * 1.5,
    justifyContent: 'center',
    alignItems: 'center',
    flex: 1,
  },
  iconCircle: {
    width: 140,
    height: 140,
    borderRadius: 70,
    backgroundColor: 'rgba(16, 185, 129, 0.05)',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: theme.spacing.xxl,
    borderWidth: 1,
    borderColor: 'rgba(16, 185, 129, 0.1)',
    position: 'relative',
  },
  pulseRing: {
    position: 'absolute',
    width: 160,
    height: 160,
    borderRadius: 80,
    borderWidth: 1,
    borderColor: 'rgba(16, 185, 129, 0.05)',
    zIndex: -1,
  },
  title: {
    fontSize: 24,
    fontWeight: '800',
    color: theme.colors.text,
    textAlign: 'center',
    marginBottom: theme.spacing.md,
    lineHeight: 30,
  },
  description: {
    fontSize: 15,
    color: theme.colors.textSecondary,
    textAlign: 'center',
    lineHeight: 22,
    paddingHorizontal: theme.spacing.sm,
  },
  skipButton: {
    position: 'absolute',
    top: 50,
    right: theme.spacing.lg,
    zIndex: 10,
    padding: theme.spacing.sm,
  },
  skipButtonText: {
    fontSize: 13,
    fontWeight: '700',
    color: theme.colors.textSecondary,
    letterSpacing: 1,
  },
  footer: {
    paddingHorizontal: theme.spacing.xl,
    paddingBottom: 40,
    width: '100%',
  },
  dotsContainer: {
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: theme.spacing.xl,
    gap: 8,
  },
  dot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: '#E5E7EB',
  },
  activeDot: {
    width: 20,
    backgroundColor: theme.colors.primary,
  },
  buttonRow: {
    height: 50,
    width: '100%',
    justifyContent: 'center',
    alignItems: 'center',
  },
  getStartedButton: {
    width: '100%',
    backgroundColor: theme.colors.primary,
    paddingVertical: theme.spacing.md,
    borderRadius: theme.borderRadius.md,
    alignItems: 'center',
    justifyContent: 'center',
    shadowColor: theme.colors.primary,
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.2,
    shadowRadius: 8,
    elevation: 3,
  },
  getStartedButtonText: {
    fontSize: 15,
    fontWeight: '700',
    color: theme.colors.black,
    letterSpacing: 0.5,
  },
});
