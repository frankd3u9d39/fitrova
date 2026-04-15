import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  Image,
  Alert,
  ActivityIndicator,
  ScrollView,
  Dimensions,
  Platform,
  StatusBar
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import * as Haptics from 'expo-haptics';
import { Video, ResizeMode, Audio } from 'expo-av';
import { Ionicons } from '@expo/vector-icons';
import { NativeStackScreenProps } from '@react-navigation/native-stack';
import { RootStackParamList } from '../../../navigation/AppNavigator';
import { completeWorkout } from '../../../services/api/workoutService';
import { theme } from '../../../theme';

const { width } = Dimensions.get('window');

type Props = NativeStackScreenProps<RootStackParamList, 'ActiveWorkout'>;

export const ActiveWorkoutScreen = ({ route, navigation }: Props) => {
  const insets = useSafeAreaInsets();
  
  // Defensive handling to prevent "undefined" convert crashes
  const params = (route.params as any) || {};
  const workout = params.workout || null;
  const userId = params.userId || 1;

  const [currentIndex, setCurrentIndex] = useState(0);
  const [loading, setLoading] = useState(false);
  const [videoError, setVideoError] = useState(false);
  
  // Premium UX: Rest Mode
  const [isRestMode, setIsRestMode] = useState(false);
  const [restTimeLeft, setRestTimeLeft] = useState(30); // 30s default

  // Guard against missing workout or exercises data
  if (!workout || !workout.exercises) {
    return (
      <View style={[styles.errorContainer, { paddingTop: insets.top }]}>
        <Ionicons name="alert-circle-outline" size={60} color="#EF4444" />
        <Text style={styles.errorText}>No workout data available.</Text>
        <TouchableOpacity style={styles.finishBtn} onPress={() => navigation.goBack()}>
          <Text style={styles.finishBtnText}>GO BACK</Text>
        </TouchableOpacity>
      </View>
    );
  }

  const currentExercise = workout.exercises[currentIndex];

  // Provide defensive duration fallback if undefined
  const defaultDuration = typeof currentExercise !== 'string' && currentExercise.duration ? currentExercise.duration : 60;

  const [timeLeft, setTimeLeft] = useState(defaultDuration);
  const [isTimerRunning, setIsTimerRunning] = useState(false);

  // Reset timer whenever the exercise changes
  useEffect(() => {
    setIsTimerRunning(false);
    setVideoError(false); // Reset video error state for the new exercise
    const fallback = typeof currentExercise !== 'string' && currentExercise.duration ? currentExercise.duration : 60;
    setTimeLeft(fallback);
  }, [currentIndex, currentExercise]);

  // Handle countdown interval
  useEffect(() => {
    let interval: ReturnType<typeof setTimeout>;

    if (isRestMode && restTimeLeft > 0) {
      interval = setInterval(() => {
        setRestTimeLeft((prev) => {
          if (prev === 4) Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
          if (prev === 1) Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
          return prev - 1;
        });
      }, 1000);
    } else if (isRestMode && restTimeLeft === 0) {
      setIsRestMode(false);
      setRestTimeLeft(30);
    } else if (isTimerRunning && timeLeft > 0) {
      interval = setInterval(() => {
        setTimeLeft((prev) => {
           if (prev === 4) Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
           return prev - 1;
        });
      }, 1000);
    } else if (timeLeft === 0 && isTimerRunning) {
      setIsTimerRunning(false);
      Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
      handleNext(); // Automated flow
    }

    return () => {
      if (interval) clearInterval(interval);
    };
  }, [isTimerRunning, timeLeft, isRestMode, restTimeLeft, currentIndex, workout.exercises.length]);

  if (!currentExercise) {
    return (
      <View style={[styles.errorContainer, { paddingTop: insets.top }]}>
        <Text style={styles.errorText}>No exercises found for this workout.</Text>
        <TouchableOpacity style={styles.finishBtn} onPress={() => navigation.goBack()}>
          <Text style={styles.finishBtnText}>GO BACK</Text>
        </TouchableOpacity>
      </View>
    );
  }

  const isLastExercise = currentIndex === workout.exercises.length - 1;
  const imageUrl = typeof currentExercise !== 'string' && currentExercise.image_url
    ? currentExercise.image_url
    : 'https://images.unsplash.com/photo-1517836357463-d25dfeac3438?w=800&q=80';

  const videoUrl = typeof currentExercise !== 'string' && currentExercise.video_url
    ? currentExercise.video_url
    : null;

  const handleNext = async () => {
    if (isLastExercise) {
      try {
        setLoading(true);
        await completeWorkout(userId || 1, workout.name, workout.duration);
        Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
        Alert.alert('Success', 'Workout completed! Great job! 💪', [
          { text: 'OK', onPress: () => navigation.goBack() }
        ]);
      } catch (err) {
        console.warn('AI service unavailable for logging', err);
        Alert.alert('Success', 'Workout completed! Great job! 💪\n\n(Offline mode)', [
          { text: 'OK', onPress: () => navigation.goBack() }
        ]);
      } finally {
        setLoading(false);
      }
    } else {
      // Premium UX: Transition to Rest Mode
      setIsRestMode(true);
      setRestTimeLeft(30);
      setCurrentIndex((prev) => prev + 1);
    }
  };

  const handlePrevious = () => {
    if (currentIndex > 0) {
      setCurrentIndex((prev) => prev - 1);
    }
  };

  const toggleTimer = () => {
    if (timeLeft === 0) setTimeLeft(defaultDuration);
    setIsTimerRunning(!isTimerRunning);
  };

  const formatTime = (seconds: number) => {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
  };

  const renderRestView = () => (
    <View style={[styles.restContainer, { paddingTop: insets.top }]}>
      <View style={styles.restHeader}>
        <Text style={styles.restLabel}>TAKE A BREATH</Text>
        <Text style={styles.restTimer}>{restTimeLeft}s</Text>
      </View>

      <View style={styles.upNextCard}>
        <Text style={styles.upNextLabel}>UP NEXT</Text>
        <Image source={{ uri: imageUrl }} style={styles.upNextImage} />
        <Text style={styles.upNextTitle}>
          {typeof currentExercise === 'string' ? currentExercise : currentExercise.name}
        </Text>
        <Text style={styles.upNextDetails}>
          {typeof currentExercise !== 'string' && currentExercise.sets ? `${currentExercise.sets} Sets • ` : ''}
          {typeof currentExercise !== 'string' && currentExercise.reps ? `${currentExercise.reps} Reps` : ''}
        </Text>
      </View>

      <TouchableOpacity 
        style={styles.skipRestBtn}
        onPress={() => setIsRestMode(false)}
      >
        <Text style={styles.skipRestText}>SKIP REST</Text>
        <Ionicons name="play-skip-forward" size={18} color="#10B981" />
      </TouchableOpacity>
    </View>
  );

  if (isRestMode) return renderRestView();

  return (
    <View style={styles.container}>
      <StatusBar barStyle="dark-content" backgroundColor="#F8FAFC" />

      {/* Static Header fixed at top above everything */}
      <View style={[styles.header, { paddingTop: Math.max(insets.top, 10) + 10 }]}>
        <TouchableOpacity style={styles.iconButton} onPress={() => navigation.goBack()}>
          <Ionicons name="close" size={24} color="#1E293B" />
        </TouchableOpacity>
        <View style={styles.progressPill}>
          <Text style={styles.progressText}>
            {currentIndex + 1} of {workout.exercises.length}
          </Text>
        </View>
        <View style={{ width: 44 }} />
      </View>

      {/* Scrollable Container so the image stays visible but scrolls naturally */}
      <ScrollView
        contentContainerStyle={styles.scrollContent}
        showsVerticalScrollIndicator={false}
      >

        <View style={styles.heroWrapper}>
          {videoUrl && !videoError ? (
            <Video
              source={{ uri: videoUrl }}
              style={styles.heroImage}
              resizeMode={ResizeMode.CONTAIN}
              shouldPlay={true}
              isLooping={true}
              isMuted={true}
              onError={(error) => {
                console.error('❌ VIDEO PLAYBACK FAILED');
                console.error('🔗 URL Attempted:', videoUrl);
                console.error('🔍 Internal Error:', error);
                setVideoError(true);
              }}
            />
          ) : (
            <Image source={{ uri: imageUrl }} style={styles.heroImage} resizeMode="contain" />
          )}
        </View>

        <View style={styles.surface}>

          <View style={styles.titleWrapper}>
            <Text style={styles.exerciseName}>
              {typeof currentExercise === 'string' ? currentExercise : currentExercise.name}
            </Text>
          </View>

          <View style={styles.metricsRow}>
            {typeof currentExercise !== 'string' && Boolean(currentExercise.sets) ? (
              <View style={styles.metricCard}>
                <Ionicons name="layers-outline" size={20} color="#10B981" />
                <Text style={styles.metricVal}>{currentExercise.sets}</Text>
                <Text style={styles.metricLabel}>SETS</Text>
              </View>
            ) : null}
            {typeof currentExercise !== 'string' && Boolean(currentExercise.reps) ? (
              <View style={styles.metricCard}>
                <Ionicons name="repeat-outline" size={20} color="#10B981" />
                <Text style={styles.metricVal}>{currentExercise.reps}</Text>
                <Text style={styles.metricLabel}>REPS</Text>
              </View>
            ) : null}
          </View>

          {/* Interactive Timer Section */}
          <View style={styles.timerContainer}>
            <Text style={styles.timerDisplay}>{formatTime(timeLeft)}</Text>

            <TouchableOpacity
              style={[styles.timerButton, isTimerRunning ? styles.timerButtonActive : styles.timerButtonIdle]}
              onPress={toggleTimer}
            >
              <Ionicons name={isTimerRunning ? "pause" : "play"} size={20} color="#FFFFFF" />
              <Text style={styles.timerButtonText}>
                {isTimerRunning ? "PAUSE TIMER" : (timeLeft === 0 ? "RESTART TIMER" : "START TIMER")}
              </Text>
            </TouchableOpacity>
          </View>

          {typeof currentExercise !== 'string' && Boolean(currentExercise.instructions) ? (
            <View style={styles.instructionsWrapper}>
              <View style={styles.instructionsHeader}>
                <Ionicons name="school-outline" size={20} color="#10B981" />
                <Text style={styles.instructionsTitle}>How to Perform</Text>
              </View>
              <Text style={styles.instructionsText}>{currentExercise.instructions}</Text>
            </View>
          ) : null}

          {/* Spacer so the user can comfortably scroll to bottom above footer */}
          <View style={{ height: 120 }} />

        </View>
      </ScrollView>

      {/* Floating Bottom Navigator */}
      <View style={[styles.footer, { paddingBottom: Math.max(insets.bottom, 20) }]}>
        <TouchableOpacity
          style={[styles.navBtnWrapper, currentIndex === 0 && styles.navBtnWrapperDisabled]}
          onPress={handlePrevious}
          disabled={currentIndex === 0 || loading}
        >
          <Ionicons name="chevron-back" size={24} color={currentIndex === 0 ? "#94A3B8" : "#1E293B"} />
        </TouchableOpacity>

        <TouchableOpacity
          style={styles.finishBtn}
          onPress={handleNext}
          disabled={loading}
        >
          {loading ? (
            <ActivityIndicator color="#FFFFFF" />
          ) : (
            <>
              <Text style={styles.finishBtnText}>
                {isLastExercise ? 'COMPLETE SESSION' : 'NEXT EXERCISE'}
              </Text>
              <Ionicons name={isLastExercise ? "checkmark-circle" : "chevron-forward"} size={20} color="#FFFFFF" />
            </>
          )}
        </TouchableOpacity>
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#F8FAFC',
  },
  heroWrapper: {
    width: '100%',
    height: 280, // slightly shorter since it's pushed down
    backgroundColor: '#F8FAFC',
    marginTop: 10,
  },
  heroImage: {
    width: '100%',
    height: '100%',
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 20,
    paddingBottom: 10,
    backgroundColor: '#F8FAFC',
    zIndex: 10,
  },
  iconButton: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: 'rgba(255,255,255,0.85)',
    justifyContent: 'center',
    alignItems: 'center',
  },
  progressPill: {
    backgroundColor: 'rgba(255,255,255,0.95)',
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 20,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 2,
  },
  progressText: {
    color: '#0F172A',
    fontWeight: '800',
    fontSize: 14,
    letterSpacing: 1,
  },
  scrollContent: {
    flexGrow: 1,
  },
  surface: {
    backgroundColor: '#FFFFFF',
    borderTopLeftRadius: 36,
    borderTopRightRadius: 36,
    paddingHorizontal: 24,
    paddingTop: 32,
    marginTop: -32, // Overlaps the image slightly
    flex: 1, // Let it fill the rest space
    shadowColor: '#000',
    shadowOffset: { width: 0, height: -4 },
    shadowOpacity: 0.05,
    shadowRadius: 10,
    elevation: 10,
  },
  titleWrapper: {
    marginBottom: 28,
  },
  exerciseName: {
    fontSize: 32,
    fontWeight: '900',
    color: '#0F172A',
    textAlign: 'center',
  },
  metricsRow: {
    flexDirection: 'row',
    justifyContent: 'center',
    gap: 16,
    marginBottom: 32,
  },
  metricCard: {
    flex: 1,
    backgroundColor: '#F1F5F9',
    borderRadius: 20,
    paddingVertical: 20,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: '#E2E8F0',
  },
  metricVal: {
    fontSize: 26,
    fontWeight: '900',
    color: '#1E293B',
    marginTop: 8,
  },
  metricLabel: {
    fontSize: 12,
    fontWeight: '800',
    color: '#64748B',
    letterSpacing: 1,
    marginTop: 4,
  },
  timerContainer: {
    alignItems: 'center',
    backgroundColor: '#F8FAFC',
    borderRadius: 24,
    padding: 32,
    marginBottom: 32,
    borderWidth: 1,
    borderColor: '#CBD5E1',
  },
  timerDisplay: {
    fontSize: 64,
    fontWeight: '900',
    color: '#10B981',
    fontVariant: ['tabular-nums'],
    marginBottom: 20,
  },
  timerButton: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 32,
    paddingVertical: 16,
    borderRadius: 30,
    gap: 8,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.2,
    shadowRadius: 8,
    elevation: 4,
  },
  timerButtonIdle: {
    backgroundColor: '#1E293B', // dark subtle button on light theme
  },
  timerButtonActive: {
    backgroundColor: '#EF4444',
  },
  timerButtonText: {
    color: '#FFFFFF',
    fontWeight: '800',
    letterSpacing: 1,
    fontSize: 14,
  },
  instructionsWrapper: {
    backgroundColor: '#ECFDF5',
    borderRadius: 20,
    padding: 24,
    borderWidth: 1,
    borderColor: '#A7F3D0',
  },
  instructionsHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    marginBottom: 12,
  },
  instructionsTitle: {
    fontSize: 16,
    fontWeight: '800',
    color: '#059669',
    letterSpacing: 0.5,
  },
  instructionsText: {
    fontSize: 15,
    lineHeight: 24,
    color: '#064E3B',
    fontWeight: '500',
  },
  footer: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    flexDirection: 'row',
    paddingHorizontal: 20,
    gap: 12,
    paddingTop: 16,
  },
  navBtnWrapper: {
    width: 60,
    height: 60,
    borderRadius: 30,
    backgroundColor: '#F8FAFC',
    justifyContent: 'center',
    alignItems: 'center',
    borderWidth: 1,
    borderColor: '#E2E8F0',
  },
  navBtnWrapperDisabled: {
    opacity: 0.5,
  },
  finishBtn: {
    flex: 1,
    flexDirection: 'row',
    height: 60,
    borderRadius: 30,
    backgroundColor: '#10B981',
    justifyContent: 'center',
    alignItems: 'center',
    gap: 10,
    shadowColor: '#10B981',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.3,
    shadowRadius: 10,
    elevation: 6,
  },
  finishBtnText: {
    color: '#FFFFFF',
    fontWeight: '800',
    fontSize: 15,
    letterSpacing: 1,
  },
  errorContainer: {
    flex: 1,
    backgroundColor: '#F8FAFC',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 32,
    gap: 24,
  },
  errorText: {
    color: '#64748B',
    fontSize: 16,
  },
  // Rest Mode Styles
  restContainer: {
    flex: 1,
    backgroundColor: '#FFFFFF', // Light background as requested
    alignItems: 'center',
    justifyContent: 'center',
    padding: 30,
  },
  restHeader: {
    alignItems: 'center',
    marginBottom: 40,
  },
  restLabel: {
    color: '#64748B',
    fontSize: 14,
    fontWeight: '800',
    letterSpacing: 2,
    marginBottom: 10,
  },
  restTimer: {
    color: '#10B981',
    fontSize: 80,
    fontWeight: '900',
  },
  upNextCard: {
    backgroundColor: '#F8FAFC',
    width: '100%',
    borderRadius: 32,
    padding: 24,
    alignItems: 'center',
    marginBottom: 40,
    borderWidth: 1,
    borderColor: '#E2E8F0',
  },
  upNextLabel: {
    color: '#94A3B8',
    fontSize: 12,
    fontWeight: '800',
    letterSpacing: 1,
    marginBottom: 16,
  },
  upNextImage: {
    width: '100%',
    height: 180,
    borderRadius: 20,
    marginBottom: 20,
  },
  upNextTitle: {
    color: '#0F172A',
    fontSize: 24,
    fontWeight: '800',
    textAlign: 'center',
    marginBottom: 8,
  },
  upNextDetails: {
    color: '#10B981',
    fontSize: 14,
    fontWeight: '700',
  },
  skipRestBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingVertical: 15,
    paddingHorizontal: 30,
    borderRadius: 30,
    borderWidth: 1,
    borderColor: '#10B981',
  },
  skipRestText: {
    color: '#10B981',
    fontSize: 14,
    fontWeight: '800',
    letterSpacing: 1,
  }
});
