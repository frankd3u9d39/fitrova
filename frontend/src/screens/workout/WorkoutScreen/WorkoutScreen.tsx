import { SafeAreaView } from 'react-native-safe-area-context';
import { CustomAlert } from '../../../components/common/CustomAlert';
import React, { useState, useEffect, useRef } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  ActivityIndicator,
  Alert,
  ImageBackground,
  Animated
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { getWorkoutRecommendations, completeWorkout, WorkoutRecommendation, generateWorkoutDetails } from '../../../services/api/workoutService';
import { useNavigation, useRoute, RouteProp, useFocusEffect } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { RootStackParamList, MainTabParamList } from '../../../navigation/types';
import { notificationService, Notification } from '../../../services/api/notificationService';
import { AICoachModal } from '../../../components/common/AICoachModal';
import { theme } from '../../../theme';
import AsyncStorage from '@react-native-async-storage/async-storage';

// Module-level cache to persist data across tab switches (unmounts/mounts)
let sessionWorkoutCache: WorkoutRecommendation | null = null;
let sessionCacheDate: string | null = null;

type WorkoutScreenNavigationProp = NativeStackNavigationProp<RootStackParamList>;
type WorkoutScreenRouteProp = RouteProp<MainTabParamList, 'Workout'>;

export const WorkoutScreen = () => {
  const navigation = useNavigation<WorkoutScreenNavigationProp>();
  const route = useRoute<WorkoutScreenRouteProp>();
  const [workoutData, setWorkoutData] = useState<WorkoutRecommendation | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [loadingStep, setLoadingStep] = useState(0);
  const [isInitialLoad, setIsInitialLoad] = useState(true);
  const progressAnim = useRef(new Animated.Value(0)).current;
  const pulseAnim = useRef(new Animated.Value(1)).current;
  
  const userId = route.params?.userId || 1;

  // Notification states
  const [latestNotification, setLatestNotification] = useState<Notification | null>(null);
  const [showAIModal, setShowAIModal] = useState(false);
  const [darkTheme, setDarkTheme] = useState(false);

  // Dynamic theme colors
  const colors = {
    background: darkTheme ? '#0F172A' : '#F9FAFB', 
    cardBg: darkTheme ? '#1E293B' : '#FFFFFF',     
    text: darkTheme ? '#F8FAFC' : '#1F2937',       
    textSecondary: darkTheme ? '#94A3B8' : '#6B7280', 
    border: darkTheme ? '#334155' : '#F3F4F6',     
    separator: darkTheme ? '#334155' : '#F3F4F6',
  };

  useFocusEffect(
    React.useCallback(() => {
      (async () => {
        try {
          const saved = await AsyncStorage.getItem(`user_prefs_${userId}`);
          if (saved) {
            const prefs = JSON.parse(saved);
            if (prefs.darkTheme !== undefined) {
              setDarkTheme(prefs.darkTheme);
            }
          }
        } catch (e) {}
      })();
    }, [userId])
  );

  const loadingSteps = [
    { text: 'Analyzing your fitness profile...', icon: 'person-outline' },
    { text: 'Calculating optimal intensity...', icon: 'analytics-outline' },
    { text: 'Selecting exercises for you...', icon: 'barbell-outline' },
    { text: 'Finalizing your workout plan...', icon: 'checkmark-circle-outline' }
  ];

  const handleToolPress = (toolId: string) => {
    switch (toolId) {
      case '1':
        navigation.navigate('FormCheck' as any, { userId });
        break;
      case '3':
        navigation.navigate('Achievements' as any);
        break;
      case '5':
        navigation.navigate('YouTubeAnalysis' as any);
        break;
      default:
        // 'Plan My Session' forces a fresh generation
        loadWorkoutData(true);
        break;
    }
  };

  useEffect(() => {
    const initializeScreen = async () => {
      try {
        await loadWorkoutData(false);
      } finally {
        fetchNotifications();
      }
    };
    initializeScreen();
  }, []);

  const fetchNotifications = async () => {
    try {
      const data = await notificationService.getNotifications(userId);
      const unreads = data.notifications.filter(n => !n.is_read);
      if (unreads.length > 0) {
        setLatestNotification(unreads[0]);
        setShowAIModal(true);
      }
    } catch (error) {
      console.error('Error fetching notifications in Workout:', error);
    }
  };

  const handleDismissAIModal = async () => {
    setShowAIModal(false);
    if (latestNotification) {
      await notificationService.markAsRead(userId, latestNotification.id);
      setLatestNotification(null);
    }
  };

  useEffect(() => {
    if (loading && isInitialLoad) {
      // Animate through loading steps only on initial load
      const stepDuration = 800;
      const stepInterval = setInterval(() => {
        setLoadingStep((prev) => {
          if (prev < loadingSteps.length - 1) {
            return prev + 1;
          }
          return prev;
        });
      }, stepDuration);

      // Animate progress bar
      Animated.timing(progressAnim, {
        toValue: 100,
        duration: stepDuration * loadingSteps.length,
        useNativeDriver: false,
      }).start();

      // Pulse animation
      Animated.loop(
        Animated.sequence([
          Animated.timing(pulseAnim, {
            toValue: 1.1,
            duration: 600,
            useNativeDriver: true,
          }),
          Animated.timing(pulseAnim, {
            toValue: 1,
            duration: 600,
            useNativeDriver: true,
          }),
        ])
      ).start();

      return () => {
        clearInterval(stepInterval);
      };
    } else if (!loading) {
      progressAnim.setValue(0);
      setLoadingStep(0);
    }
  }, [loading, isInitialLoad]);

  const loadWorkoutData = async (forceRefresh: boolean = false) => {
    try {
      setLoading(true);
      const todayStr = new Date().toDateString();

      // Check session cache first unless forceRefresh is true
      if (!forceRefresh && sessionWorkoutCache && sessionCacheDate === todayStr) {
        console.log('⚡ Loading workout from memory session cache');
        setWorkoutData(sessionWorkoutCache);
        setError(null);
        return;
      }

      console.log(`📡 Fetching workout recommendations (Force: ${forceRefresh})`);
      const data = await getWorkoutRecommendations(userId);
      setWorkoutData(data);
      
      // Store in memory session cache
      sessionWorkoutCache = data;
      sessionCacheDate = todayStr;
      
      setError(null);
    } catch (err) {
      // Error is already handled in service with fallback data
      console.error('Workout data error:', err);
    } finally {
      setLoading(false);
      setIsInitialLoad(false);
    }
  };

  const handleStartWorkout = () => {
    if (!workoutData?.todays_workout) return;
    
    navigation.navigate('ActiveWorkout', {
      workout: workoutData.todays_workout,
      userId: userId
    });
  };

  if (loading) {
    const progressWidth = progressAnim.interpolate({
      inputRange: [0, 100],
      outputRange: ['0%', '100%'],
    });

    return (
      <SafeAreaView style={[styles.container, { backgroundColor: colors.background }]}>
        <View style={[styles.loadingContainer, { backgroundColor: colors.background }]}>
          <Animated.View style={[
            styles.loadingIconContainer, 
            { transform: [{ scale: pulseAnim }] },
            darkTheme && { backgroundColor: '#1E293B', shadowColor: '#10B981' }
          ]}>
            <Ionicons name="barbell" size={64} color="#10B981" />
          </Animated.View>
          
          <View style={styles.loadingStepsContainer}>
            {loadingSteps.map((step, index) => (
              <View key={index} style={styles.loadingStepRow}>
                <View style={[
                  styles.stepIconContainer,
                  index <= loadingStep && styles.stepIconActive,
                  darkTheme && index > loadingStep && { backgroundColor: '#334155' }
                ]}>
                  {index < loadingStep ? (
                    <Ionicons name="checkmark" size={16} color="#FFFFFF" />
                  ) : index === loadingStep ? (
                    <ActivityIndicator size="small" color="#FFFFFF" />
                  ) : (
                    <Ionicons name={step.icon as any} size={16} color={colors.textSecondary} />
                  )}
                </View>
                <Text style={[
                  styles.loadingStepText,
                  { color: colors.textSecondary },
                  index === loadingStep && [styles.loadingStepTextActive, { color: colors.text }]
                ]}>
                  {step.text}
                </Text>
              </View>
            ))}
          </View>

          <View style={styles.progressBarWrapper}>
            <View style={[styles.progressBarBackground, darkTheme && { backgroundColor: '#334155' }]}>
              <Animated.View style={[styles.progressBarFill, { width: progressWidth }]} />
            </View>
            <Text style={styles.progressText}>
              {Math.min(Math.round((loadingStep + 1) / loadingSteps.length * 100), 100)}%
            </Text>
          </View>

          <Text style={[styles.motivationalText, { color: colors.textSecondary }]}>
            💪 Preparing your personalized workout...
          </Text>
        </View>
      </SafeAreaView>
    );
  }

  if (error || !workoutData) {
    return (
      <SafeAreaView style={[styles.container, { backgroundColor: colors.background }]}>
        <View style={[styles.errorContainer, { backgroundColor: colors.background }]}>
          <Ionicons name="alert-circle" size={48} color="#EF4444" />
          <Text style={[styles.errorText, { color: colors.textSecondary }]}>{error || 'Unable to load workout data'}</Text>
          <TouchableOpacity style={styles.retryButton} onPress={() => loadWorkoutData(true)}>
            <Text style={styles.retryButtonText}>Retry</Text>
          </TouchableOpacity>
        </View>
      </SafeAreaView>
    );
  }

  const aiTools = [
    { id: '1', title: 'Check Form with AI', icon: 'camera-outline' },
    { id: '5', title: 'Analyze Video', icon: 'logo-youtube' },
    { id: '3', title: 'View Past PRs', icon: 'trophy-outline' },
  ];

  const formatDate = (dateStr: string) => {
    const date = new Date(dateStr);
    const today = new Date();
    const tomorrow = new Date(today);
    tomorrow.setDate(tomorrow.getDate() + 1);
    
    if (date.toDateString() === tomorrow.toDateString()) {
      return 'TOMORROW • 08:00 AM';
    }
    return date.toLocaleDateString('en-US', { 
      weekday: 'short', 
      month: 'short', 
      day: 'numeric' 
    }).toUpperCase();
  };


  return (
    <SafeAreaView style={[styles.container, { backgroundColor: colors.background }]}>
      <ScrollView
        style={[styles.scrollView, { backgroundColor: colors.background }]}
        contentContainerStyle={styles.scrollContent}
        showsVerticalScrollIndicator={false}
      >
        {/* Header Status */}
        <View style={styles.statusContainer}>
          <View style={[styles.recoveryBadge, darkTheme && { backgroundColor: '#064E3B' }]}>
            <Ionicons name="flash" size={14} color="#10B981" />
            <Text style={styles.recoveryText}>Ready • Recovery {workoutData.recovery_score}%</Text>
          </View>
        </View>

        {workoutData.todays_workout ? (
          <ImageBackground 
            source={{ uri: 'https://images.unsplash.com/photo-1517836357463-d25dfeac3438?w=800&q=80' }} 
            style={styles.programCard}
            imageStyle={{ borderRadius: 24 }}
          >
            <View style={styles.programCardOverlay}>
              <View style={styles.programImageContainer}>
                <View style={styles.programImage}>
                  <Ionicons name="barbell" size={80} color="#10B981" />
                </View>
              </View>
              <Text style={styles.programLabel}>TODAY'S PROGRAM</Text>
              <Text style={styles.programTitle}>{workoutData.todays_workout.name}</Text>
              <View style={styles.programDetails}>
                <View style={styles.programDetailItem}>
                  <Ionicons name="fitness-outline" size={18} color="#10B981" />
                  <Text style={styles.programDetailText}>
                    {workoutData.todays_workout.exercises_count} exercises
                  </Text>
                </View>
                <View style={styles.programDetailItem}>
                  <Ionicons name="time-outline" size={18} color="#10B981" />
                  <Text style={styles.programDetailText}>
                    ~{workoutData.todays_workout.duration} mins
                  </Text>
                </View>
              </View>
              <TouchableOpacity style={styles.startButton} onPress={handleStartWorkout}>
                <Text style={styles.startButtonText}>START WORKOUT</Text>
                <Ionicons name="play" size={20} color="#FFFFFF" />
              </TouchableOpacity>
            </View>
          </ImageBackground>
        ) : (
          <View style={[styles.restDayCard, darkTheme && { backgroundColor: '#1E293B' }]}>
            <Ionicons name="bed-outline" size={60} color="#10B981" />
            <Text style={[styles.restDayTitle, darkTheme && { color: '#F8FAFC' }]}>Rest Day</Text>
            <Text style={[styles.restDayText, { color: colors.textSecondary }]}>Recovery is just as important as training</Text>
          </View>
        )}

        {/* Weekly Goal */}
        <TouchableOpacity 
          style={[styles.weeklyGoalCard, darkTheme && { backgroundColor: '#1E293B' }]}
          onPress={() => navigation.navigate('Schedule' as any)}
        >
          <View style={styles.weeklyGoalHeader}>
            <Text style={[styles.weeklyGoalLabel, { color: colors.textSecondary }]}>WEEKLY GOAL</Text>
            <Text style={[styles.weeklyGoalProgress, { color: colors.text }]}>
              {workoutData.weekly_progress.completed}/{workoutData.weekly_progress.goal}
            </Text>
          </View>
          <View style={[styles.progressBarContainer, darkTheme && { backgroundColor: '#334155' }]}>
            <View 
              style={[
                styles.progressBar, 
                { width: `${(workoutData.weekly_progress.completed / workoutData.weekly_progress.goal) * 100}%` }
              ]} 
            />
          </View>
        </TouchableOpacity>

        {/* AI Tools */}
        <View style={styles.section}>
          <View style={styles.sectionHeader}>
            <Ionicons name="sparkles" size={20} color="#10B981" />
            <Text style={[styles.sectionTitle, { color: colors.text }]}>AI Tools</Text>
          </View>
          <View style={styles.toolsGrid}>
            {aiTools.map((tool) => (
              <TouchableOpacity 
                key={tool.id} 
                style={[styles.toolCard, darkTheme && { backgroundColor: '#1E293B' }]}
                onPress={() => handleToolPress(tool.id)}
              >
                <View style={[styles.toolIcon, darkTheme && { backgroundColor: '#064E3B' }]}>
                  <Ionicons name={tool.icon as any} size={24} color="#10B981" />
                </View>
                <Text style={[styles.toolTitle, { color: colors.text }]}>{tool.title}</Text>
              </TouchableOpacity>
            ))}
          </View>
        </View>

        {/* Upcoming Workouts */}
        <View style={styles.section}>
          <View style={styles.sectionHeaderRow}>
            <Text style={[styles.sectionTitle, { color: colors.text }]}>Upcoming Workouts</Text>
          </View>
          <View style={styles.workoutsContainer}>
            {(workoutData.upcoming_workouts || []).map((workout, index) => (
              <TouchableOpacity 
                key={index} 
                style={[styles.workoutCard, darkTheme && { backgroundColor: '#1E293B' }]}
                onPress={async () => {
                  if (workout && (workout as any).exercises) {
                    navigation.navigate('ActiveWorkout' as any, { workout, userId });
                  } else {
                    // Start generation flow
                    try {
                      setLoading(true);
                      const fullWorkout = await generateWorkoutDetails(userId, (workout as any).name || 'Daily Focus');
                      if (fullWorkout) {
                        navigation.navigate('ActiveWorkout' as any, { workout: fullWorkout, userId });
                      } else {
                        CustomAlert.alert("Error", "Could not generate session. Please check your connection.");
                      }
                    } finally {
                      setLoading(false);
                    }
                  }
                }}
              >
                <View style={styles.workoutLeft}>
                  <View style={[styles.workoutIconContainer, darkTheme && { backgroundColor: '#334155' }]}>
                    <Ionicons name="calendar-outline" size={24} color={colors.textSecondary} />
                  </View>
                  <View style={styles.workoutInfo}>
                    <Text style={[styles.workoutDate, { color: colors.textSecondary }]}>
                      {workout.scheduled_date ? formatDate(workout.scheduled_date) : 'TBD'}
                    </Text>
                    <Text style={[styles.workoutTitle, { color: colors.text }]}>{workout.name}</Text>
                    <Text style={[styles.workoutDetails, { color: colors.textSecondary }]}>
                      {workout.exercises_count} Exercises • {workout.duration}m
                    </Text>
                  </View>
                </View>
                <TouchableOpacity 
                  style={styles.workoutMenu}
                  onPress={(e) => {
                    e.stopPropagation();
                    Alert.alert(
                      workout.name,
                      "Workout Options",
                      [
                        { text: "View Details", onPress: () => console.log("Details") },
                        { text: "Remove", style: "destructive", onPress: () => console.log("Remove") },
                        { text: "Cancel", style: "cancel" }
                      ]
                    );
                  }}
                >
                  <Ionicons name="ellipsis-horizontal" size={20} color={colors.textSecondary} />
                </TouchableOpacity>
              </TouchableOpacity>
            ))}
          </View>
        </View>

        <View style={styles.bottomSpacer} />
      </ScrollView>

      {/* AI Pop-up Coach Modal */}
      <AICoachModal
        visible={showAIModal}
        notification={latestNotification}
        onDismiss={handleDismissAIModal}
      />
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#F9FAFB',
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: 32,
    gap: 32,
  },
  loadingIconContainer: {
    width: 120,
    height: 120,
    borderRadius: 60,
    backgroundColor: '#F0FDF4',
    justifyContent: 'center',
    alignItems: 'center',
    ...theme.shadows.lg,
    shadowColor: '#10B981', // Keep custom brand color glow
  },
  loadingStepsContainer: {
    width: '100%',
    gap: 16,
  },
  loadingStepRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  stepIconContainer: {
    width: 32,
    height: 32,
    borderRadius: 16,
    backgroundColor: '#F3F4F6',
    justifyContent: 'center',
    alignItems: 'center',
  },
  stepIconActive: {
    backgroundColor: '#10B981',
  },
  loadingStepText: {
    fontSize: 14,
    color: '#9CA3AF',
    flex: 1,
  },
  loadingStepTextActive: {
    color: '#1F2937',
    fontWeight: '600',
  },
  progressBarWrapper: {
    width: '100%',
    gap: 8,
  },
  progressBarBackground: {
    height: 8,
    backgroundColor: '#F3F4F6',
    borderRadius: 4,
    overflow: 'hidden',
  },
  progressBarFill: {
    height: '100%',
    backgroundColor: '#10B981',
    borderRadius: 4,
  },
  progressText: {
    fontSize: 12,
    fontWeight: '700',
    color: '#10B981',
    textAlign: 'center',
  },
  motivationalText: {
    fontSize: 14,
    color: '#6B7280',
    textAlign: 'center',
    fontWeight: '500',
  },
  loadingText: {
    fontSize: 14,
    color: '#6B7280',
  },
  errorContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    gap: 16,
    paddingHorizontal: 32,
  },
  errorText: {
    fontSize: 14,
    color: '#6B7280',
    textAlign: 'center',
  },
  retryButton: {
    backgroundColor: '#10B981',
    paddingHorizontal: 24,
    paddingVertical: 12,
    borderRadius: 12,
    marginTop: 8,
  },
  retryButtonText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#FFFFFF',
  },
  missedAlert: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#FEF3C7',
    padding: 12,
    borderRadius: 12,
    marginBottom: 16,
    gap: 8,
  },
  missedText: {
    fontSize: 13,
    fontWeight: '600',
    color: '#92400E',
    flex: 1,
  },
  restDayCard: {
    backgroundColor: '#FFFFFF',
    borderRadius: 24,
    padding: 32,
    marginBottom: 16,
    alignItems: 'center',
    ...theme.shadows.md,
  },
  restDayTitle: {
    ...theme.typography.h2,
    color: '#1F2937',
    marginTop: 16,
    marginBottom: 8,
  },
  restDayText: {
    fontSize: 14,
    color: '#6B7280',
    textAlign: 'center',
  },
  scrollView: {
    flex: 1,
  },
  scrollContent: {
    paddingHorizontal: 20,
    paddingTop: 50,
  },
  statusContainer: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 20,
  },
  statusBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#F0FDF4',
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 20,
    borderWidth: 1,
    borderColor: '#10B981',
    gap: 6,
  },
  statusDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: '#10B981',
  },
  statusText: {
    fontSize: 12,
    fontWeight: '700',
    color: '#10B981',
    letterSpacing: 0.5,
  },
  recoveryBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#F0FDF4',
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 20,
    gap: 4,
  },
  recoveryText: {
    fontSize: 12,
    fontWeight: '600',
    color: '#10B981',
  },
  programCard: {
    borderRadius: 24,
    marginBottom: 16,
    overflow: 'hidden',
  },
  programCardOverlay: {
    backgroundColor: 'rgba(15, 23, 42, 0.7)',
    padding: 24,
    alignItems: 'center',
  },
  programImageContainer: {
    marginBottom: 16,
  },
  programImage: {
    width: 120,
    height: 120,
    borderRadius: 60,
    backgroundColor: 'rgba(255, 255, 255, 0.1)',
    justifyContent: 'center',
    alignItems: 'center',
  },
  programLabel: {
    fontSize: 12,
    fontWeight: '700',
    color: '#10B981',
    letterSpacing: 1,
    marginBottom: 8,
  },
  programTitle: {
    ...theme.typography.h2,
    color: '#FFFFFF',
    marginBottom: 16,
  },
  programDetails: {
    flexDirection: 'row',
    gap: 20,
    marginBottom: 20,
  },
  programDetailItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  programDetailText: {
    fontSize: 14,
    color: '#D1D5DB',
    fontWeight: '500',
  },
  startButton: {
    flexDirection: 'row',
    backgroundColor: '#10B981',
    paddingHorizontal: 32,
    paddingVertical: 16,
    borderRadius: 16,
    alignItems: 'center',
    gap: 8,
    width: '100%',
    justifyContent: 'center',
  },
  startButtonText: {
    fontSize: 16,
    fontWeight: '700',
    color: '#FFFFFF',
    letterSpacing: 0.5,
  },
  achievementCard: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    backgroundColor: '#FFFFFF',
    borderRadius: 16,
    padding: 16,
    marginBottom: 16,
    ...theme.shadows.sm,
  },
  achievementLeft: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    flex: 1,
  },
  achievementIcon: {
    width: 48,
    height: 48,
    borderRadius: 24,
    backgroundColor: '#F0FDF4',
    justifyContent: 'center',
    alignItems: 'center',
  },
  achievementLabel: {
    fontSize: 11,
    fontWeight: '700',
    color: '#6B7280',
    letterSpacing: 0.5,
    marginBottom: 4,
  },
  achievementTitle: {
    fontSize: 15,
    fontWeight: '600',
    color: '#1F2937',
  },
  achievementValue: {
    color: '#10B981',
    fontWeight: '700',
  },
  weeklyGoalCard: {
    backgroundColor: '#FFFFFF',
    borderRadius: 16,
    padding: 16,
    marginBottom: 24,
    ...theme.shadows.md,
  },
  weeklyGoalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
  },
  weeklyGoalLabel: {
    fontSize: 12,
    fontWeight: '700',
    color: '#6B7280',
    letterSpacing: 0.5,
  },
  weeklyGoalProgress: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#1F2937',
  },
  progressBarContainer: {
    height: 8,
    backgroundColor: '#F3F4F6',
    borderRadius: 4,
    overflow: 'hidden',
  },
  progressBar: {
    height: '100%',
    backgroundColor: '#10B981',
    borderRadius: 4,
  },
  section: {
    marginBottom: 24,
  },
  sectionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 16,
    gap: 8,
  },
  sectionHeaderRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 16,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: '#1F2937',
  },
  viewScheduleText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#10B981',
  },
  toolsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
  },
  toolCard: {
    width: '48%',
    backgroundColor: '#FFFFFF',
    borderRadius: 16,
    padding: 16,
    alignItems: 'center',
    gap: 12,
    ...theme.shadows.sm,
  },
  toolIcon: {
    width: 48,
    height: 48,
    borderRadius: 24,
    backgroundColor: '#F0FDF4',
    justifyContent: 'center',
    alignItems: 'center',
  },
  toolTitle: {
    fontSize: 13,
    fontWeight: '600',
    color: '#1F2937',
    textAlign: 'center',
  },
  workoutsContainer: {
    gap: 12,
  },
  workoutCard: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    backgroundColor: '#FFFFFF',
    borderRadius: 16,
    padding: 16,
    ...theme.shadows.sm,
  },
  workoutLeft: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    flex: 1,
  },
  workoutIconContainer: {
    width: 48,
    height: 48,
    borderRadius: 24,
    backgroundColor: '#F3F4F6',
    justifyContent: 'center',
    alignItems: 'center',
  },
  workoutInfo: {
    flex: 1,
  },
  workoutDate: {
    fontSize: 11,
    fontWeight: '700',
    color: '#6B7280',
    letterSpacing: 0.5,
    marginBottom: 4,
  },
  workoutTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: '#1F2937',
    marginBottom: 4,
  },
  workoutDetails: {
    fontSize: 13,
    color: '#6B7280',
  },
  workoutMenu: {
    padding: 8,
  },
  bottomSpacer: {
    height: 100,
  },
});
