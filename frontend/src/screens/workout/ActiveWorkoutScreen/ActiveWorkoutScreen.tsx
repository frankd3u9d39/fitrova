import { CustomAlert } from '../../../components/common/CustomAlert';
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
import { useVideoPlayer, VideoView } from 'expo-video';
import { Ionicons } from '@expo/vector-icons';
import { WebView } from 'react-native-webview';
import YoutubePlayer from 'react-native-youtube-iframe';
import { NativeStackScreenProps } from '@react-navigation/native-stack';
import { RootStackParamList } from '../../../navigation/types';
import { completeWorkout } from '../../../services/api/workoutService';
import { theme } from '../../../theme';
import { Svg, Circle } from 'react-native-svg';

const COLORS = {
  PRIMARY: '#006D33',
  PRIMARY_CONTAINER: '#00D46A',
  ON_PRIMARY_CONTAINER: '#00210B',
  BACKGROUND: '#F8F9FA',
  SURFACE: '#FFFFFF',
  OUTLINE: '#6C7B6C',
  OUTLINE_VARIANT: '#BBCBB9',
  TEXT: '#191C1D',
  TEXT_VARIANT: '#3C4A3D',
};


const { width } = Dimensions.get('window');

type Props = NativeStackScreenProps<RootStackParamList, 'ActiveWorkout'>;

export const ActiveWorkoutScreen = ({ route, navigation }: Props) => {
  const insets = useSafeAreaInsets();
  
  const safeGoBack = () => {
    if (navigation.canGoBack()) {
      navigation.goBack();
    } else {
      navigation.navigate('Main' as any);
    }
  };
  
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
  const [activeTab, setActiveTab] = useState<'How To' | 'Tips'>('How To');
  
  // Guard against missing workout or exercises data
  if (!workout || !workout.exercises) {
    return (
      <View style={[styles.errorContainer, { paddingTop: insets.top }]}>
        <Ionicons name="alert-circle-outline" size={60} color="#EF4444" />
        <Text style={styles.errorText}>No workout data available.</Text>
        <TouchableOpacity style={styles.finishBtn} onPress={safeGoBack}>
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
    setVideoError(false); 
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
      setIsTimerRunning(true);
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
      handleNext(); 
    }

    return () => {
      if (interval) clearInterval(interval);
    };
  }, [isTimerRunning, timeLeft, isRestMode, restTimeLeft, currentIndex, workout.exercises.length]);

  if (!currentExercise) {
    return (
      <View style={[styles.errorContainer, { paddingTop: insets.top }]}>
        <Text style={styles.errorText}>No exercises found for this workout.</Text>
        <TouchableOpacity style={styles.finishBtn} onPress={safeGoBack}>
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

  const player = useVideoPlayer(videoUrl || '', player => {
    if (videoUrl && !videoUrl.includes('youtube.com') && !videoUrl.includes('youtu.be')) {
      player.loop = true;
      player.muted = true;
      player.play();
    }
  });

  const getYoutubeId = (url: string) => {
    if (!url) return null;
    const regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/;
    const match = url.match(regExp);
    return (match && match[2].length === 11) ? match[2] : null;
  };

  const youtubeId = videoUrl ? getYoutubeId(videoUrl) : null;
  
  const [youtubeError, setYoutubeError] = useState(false);

  useEffect(() => {
    if (youtubeId) {
      console.log('📺 [YouTube]: Found ID:', youtubeId, 'from URL:', videoUrl);
      setYoutubeError(false);
    }
  }, [youtubeId, videoUrl, currentIndex]);

  const handleNext = async () => {
    if (isLastExercise) {
      try {
        setLoading(true);
        const result = await completeWorkout(userId || 1, workout.name, workout.duration);
        Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);

        if (result.newly_unlocked && result.newly_unlocked.length > 0) {
          const badgeList = result.newly_unlocked.map((a: string) => `🏆 ${a}`).join('\n');
          CustomAlert.alert(
            '🎉 Achievement Unlocked!',
            `Workout completed! Great job! 💪\n\nNew badges earned:\n${badgeList}`,
            [{ text: 'Awesome!', onPress: safeGoBack }]
          );
        } else {
          CustomAlert.alert('Success', 'Workout completed! Great job! 💪', [
            { text: 'OK', onPress: safeGoBack }
          ]);
        }
      } catch (err) {
        console.warn('AI service unavailable for logging', err);
        CustomAlert.alert('Success', 'Workout completed! Great job! 💪\n\n(Offline mode)', [
          { text: 'OK', onPress: safeGoBack }
        ]);
      } finally {
        setLoading(false);
      }
    } else {
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
        <Text style={styles.restTimer}>{`${restTimeLeft}s`}</Text>
      </View>
      <View style={styles.upNextCard}>
        <Text style={styles.upNextLabel}>UP NEXT</Text>
        <Image source={{ uri: imageUrl }} style={styles.upNextImage} />
        <Text style={styles.upNextTitle}>{typeof currentExercise === 'string' ? currentExercise : currentExercise.name}</Text>
        <Text style={styles.upNextDetails}>
          {typeof currentExercise !== 'string' && currentExercise.sets ? `${currentExercise.sets} Sets • ` : null}
          {typeof currentExercise !== 'string' && currentExercise.reps ? `${currentExercise.reps} Reps` : null}
        </Text>
      </View>
      <TouchableOpacity 
        style={styles.skipRestBtn} 
        onPress={() => {
          setIsRestMode(false);
          setIsTimerRunning(true);
        }}
      >
        <Text style={styles.skipRestText}>SKIP REST</Text>
        <Ionicons name="play-skip-forward" size={18} color="#10B981" />
      </TouchableOpacity>
    </View>
  );

  if (isRestMode) return renderRestView();

  return (
    <View style={styles.container}>
      <StatusBar barStyle="dark-content" backgroundColor={COLORS.BACKGROUND} />
      
      {/* Top Navigation Bar */}
      <View style={[styles.header, { paddingTop: Math.max(insets.top, 10) + 10 }]}>
        <TouchableOpacity style={styles.iconButton} onPress={safeGoBack}>
          <Ionicons name="close" size={24} color={COLORS.TEXT} />
        </TouchableOpacity>
        <View style={styles.progressPill}>
          <Text style={styles.progressText}>{`${currentIndex + 1} of ${workout.exercises.length}`}</Text>
        </View>
        <View style={{ width: 44 }} />
      </View>

      <ScrollView contentContainerStyle={styles.scrollContent} showsVerticalScrollIndicator={false}>
        {/* Exercise Title */}
        <View style={styles.titleWrapper}>
          <Text style={styles.exerciseName}>
            {typeof currentExercise === 'string' ? currentExercise : currentExercise.name}
          </Text>
        </View>

        {/* Hero Video Section */}
        <View style={styles.heroWrapper}>
          {youtubeId && !youtubeError ? (
            <YoutubePlayer
              height={width * (9/16)}
              play={isTimerRunning}
              videoId={youtubeId}
              mute={true}
              host="https://www.youtube-nocookie.com"
              onChangeState={(state) => {
                if (state === 'playing') setIsTimerRunning(true);
                else if (state === 'paused' || state === 'ended') setIsTimerRunning(false);
              }}
              onError={(e) => {
                console.log('❌ [YouTube Error]:', e);
                setYoutubeError(true);
                Haptics.notificationAsync(Haptics.NotificationFeedbackType.Warning);
              }}
              initialPlayerParams={{
                loop: true,
                playlist: youtubeId,
                controls: true,
                modestbranding: true,
              }}
              webViewProps={{
                allowsFullscreenVideo: true,
                androidLayerType: 'hardware',
                origin: 'https://www.youtube-nocookie.com',
              }}
            />
          ) : videoUrl && !youtubeId ? (
            <VideoView
              player={player}
              style={styles.heroImage}
              contentFit="cover"
            />
          ) : (
            <Image source={{ uri: imageUrl }} style={styles.heroImage} />
          )}

          {/* AI Form Check Pill */}
          <TouchableOpacity 
            style={styles.formCheckPill}
            onPress={() => navigation.navigate('FormCheck' as any, { 
              userId,
              exercise: typeof currentExercise === 'string' ? currentExercise : currentExercise.name
            })}
          >
            <Ionicons name="videocam" size={16} color={COLORS.ON_PRIMARY_CONTAINER} />
            <Text style={styles.formCheckPillText}>AI Form Check</Text>
          </TouchableOpacity>
        </View>

        {/* Metrics Grid */}
        <View style={styles.metricsGrid}>
          <View style={styles.metricCard}>
            <Text 
              style={[
                styles.metricValue, 
                typeof currentExercise !== 'string' && String(currentExercise.sets || '').length > 6 
                  ? { fontSize: 12, textAlign: 'center', paddingHorizontal: 4, lineHeight: 16 } 
                  : null
              ]}
              numberOfLines={2}
            >
              {typeof currentExercise !== 'string' && currentExercise.sets ? currentExercise.sets : '3'}
            </Text>
            <Text style={styles.metricLabel}>SETS</Text>
          </View>
          
          <View style={styles.metricCard}>
            <Text 
              style={[
                styles.metricValue, 
                typeof currentExercise !== 'string' && String(currentExercise.reps || '').length > 6 
                  ? { fontSize: 12, textAlign: 'center', paddingHorizontal: 4, lineHeight: 16 } 
                  : null
              ]}
              numberOfLines={2}
            >
              {typeof currentExercise !== 'string' && currentExercise.reps ? currentExercise.reps : '15'}
            </Text>
            <Text style={styles.metricLabel}>
              {typeof currentExercise !== 'string' && String(currentExercise.reps || '').toLowerCase().includes('hold') ? 'GOAL' : 'REPS'}
            </Text>
          </View>

          <View style={styles.metricCard}>
            <View style={styles.circularTimerContainer}>
              <Svg width="64" height="64" viewBox="0 0 64 64">
                <Circle 
                  cx="32" cy="32" r="28" 
                  stroke="#E7E8E9" strokeWidth="4" fill="none" 
                />
                <Circle 
                  cx="32" cy="32" r="28" 
                  stroke={COLORS.PRIMARY_CONTAINER} strokeWidth="4" fill="none" 
                  strokeDasharray="176" 
                  strokeDashoffset={176 * (1 - timeLeft / defaultDuration)}
                  strokeLinecap="round"
                  transform="rotate(-90 32 32)"
                />
              </Svg>
              <View style={styles.timerCenter}>
                <Text style={styles.timerTextSmall}>{formatTime(timeLeft)}</Text>
              </View>
            </View>
          </View>
        </View>

        {/* Instructions Section */}
        <View style={styles.instructionsCard}>
          <View style={styles.tabHeader}>
            <TouchableOpacity 
              onPress={() => setActiveTab('How To')}
              style={[styles.tabButton, activeTab === 'How To' && styles.tabButtonActive]}
            >
              <Text style={[styles.tabText, activeTab === 'How To' && styles.tabTextActive]}>How To</Text>
            </TouchableOpacity>
            <TouchableOpacity 
              onPress={() => setActiveTab('Tips')}
              style={[styles.tabButton, activeTab === 'Tips' && styles.tabButtonActive]}
            >
              <Text style={[styles.tabText, activeTab === 'Tips' && styles.tabTextActive]}>Tips</Text>
            </TouchableOpacity>
          </View>

          <View style={styles.instructionContent}>
            {activeTab === 'How To' ? (
              typeof currentExercise !== 'string' && currentExercise.instructions ? (
                (Array.isArray(currentExercise.instructions)
                  ? currentExercise.instructions
                  : typeof currentExercise.instructions === 'string'
                  ? currentExercise.instructions
                      .split(/\d+\.|\n|•/)
                      .map(s => s.trim())
                      .filter(s => s.length > 0)
                  : []
                ).map((step: string, idx: number) => {
                  // If the step contains a colon, use it as title:desc
                  const hasColon = step.includes(':');
                  const title = hasColon ? step.split(':')[0].trim() : step.split(' ').slice(0, 2).join(' ');
                  const description = hasColon ? step.split(':')[1].trim() : step.trim();
                  
                  return (
                    <View key={idx} style={styles.stepRow}>
                      <View style={styles.stepBadge}>
                        <Text style={styles.stepBadgeText}>{idx + 1}</Text>
                      </View>
                      <View style={styles.stepTextContainer}>
                        <Text style={styles.stepTitle}>{title}</Text>
                        <Text style={styles.stepDescription}>{description}</Text>
                      </View>
                    </View>
                  );
                })
              ) : (
                <View style={styles.emptyInstructions}>
                  <Ionicons name="information-circle-outline" size={32} color={COLORS.OUTLINE} />
                  <Text style={styles.emptyText}>Follow the video demonstration for correct form and technique. Always maintain a controlled pace.</Text>
                </View>
              )
            ) : (
              <View style={styles.tipsContainer}>
                <View style={styles.tipItem}>
                  <Ionicons name="bulb-outline" size={20} color={COLORS.PRIMARY} />
                  <Text style={styles.tipText}>Focus on slow, controlled movements for maximum engagement.</Text>
                </View>
                <View style={styles.tipItem}>
                  <Ionicons name="water-outline" size={20} color={COLORS.PRIMARY} />
                  <Text style={styles.tipText}>Remember to stay hydrated between sets.</Text>
                </View>
              </View>
            )}
          </View>
        </View>

        <View style={{ height: 240 }} />
      </ScrollView>

      {/* Fixed Footer Actions */}
      <View style={[styles.footer, { paddingBottom: insets.bottom + 16 }]}>
        <TouchableOpacity 
          style={styles.primaryActionButton} 
          onPress={isTimerRunning ? toggleTimer : toggleTimer}
        >
          <Ionicons 
            name={isTimerRunning ? "pause" : "play"} 
            size={24} color={COLORS.ON_PRIMARY_CONTAINER} 
          />
          <Text style={styles.primaryActionButtonText}>
            {isTimerRunning ? "PAUSE WORKOUT" : "START WORKOUT"}
          </Text>
        </TouchableOpacity>
        
        {currentIndex > 0 && (
          <TouchableOpacity 
            style={styles.secondaryActionButton} 
            onPress={handleNext}
          >
            <Text style={styles.secondaryActionButtonText}>
              {isLastExercise ? "COMPLETE WORKOUT" : "NEXT EXERCISE"}
            </Text>
            <Ionicons name="chevron-forward" size={20} color={COLORS.TEXT} />
          </TouchableOpacity>
        )}
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: COLORS.BACKGROUND,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 20,
    paddingBottom: 12,
    backgroundColor: COLORS.BACKGROUND,
    zIndex: 10,
  },
  iconButton: {
    width: 44,
    height: 44,
    borderRadius: 22,
    justifyContent: 'center',
    alignItems: 'center',
  },
  progressPill: {
    backgroundColor: '#EDEEEF',
    paddingHorizontal: 16,
    paddingVertical: 4,
    borderRadius: 20,
  },
  progressText: {
    color: COLORS.TEXT,
    fontWeight: '600',
    fontSize: 14,
  },
  scrollContent: {
    flexGrow: 1,
    paddingHorizontal: 20,
  },
  titleWrapper: {
    marginTop: 16,
    marginBottom: 24,
  },
  exerciseName: {
    fontSize: 32,
    fontWeight: '800',
    color: COLORS.TEXT,
    textAlign: 'center',
  },
  heroWrapper: {
    width: '100%',
    aspectRatio: 16 / 9,
    backgroundColor: '#000',
    borderRadius: 24,
    overflow: 'hidden',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.1,
    shadowRadius: 12,
    elevation: 4,
    marginBottom: 24,
  },
  heroImage: {
    width: '100%',
    height: '100%',
  },
  formCheckPill: {
    position: 'absolute',
    bottom: 16,
    right: 16,
    backgroundColor: COLORS.PRIMARY_CONTAINER,
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 24,
    gap: 8,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.15,
    shadowRadius: 8,
    elevation: 4,
  },
  formCheckPillText: {
    color: COLORS.ON_PRIMARY_CONTAINER,
    fontWeight: '700',
    fontSize: 14,
  },
  metricsGrid: {
    flexDirection: 'row',
    gap: 12,
    marginTop: 24,
    marginBottom: 32,
  },
  metricCard: {
    flex: 1,
    backgroundColor: COLORS.SURFACE,
    borderRadius: 24,
    paddingVertical: 16,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: COLORS.OUTLINE_VARIANT,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 4,
    elevation: 1,
  },
  metricValue: {
    fontSize: 24,
    fontWeight: '700',
    color: COLORS.TEXT,
  },
  metricLabel: {
    fontSize: 10,
    fontWeight: '600',
    color: COLORS.OUTLINE,
    letterSpacing: 1,
    marginTop: 2,
  },
  circularTimerContainer: {
    width: 64,
    height: 64,
    justifyContent: 'center',
    alignItems: 'center',
  },
  timerCenter: {
    ...StyleSheet.absoluteFillObject,
    justifyContent: 'center',
    alignItems: 'center',
  },
  timerTextSmall: {
    fontSize: 14,
    fontWeight: '800',
    color: COLORS.PRIMARY,
  },
  instructionsCard: {
    backgroundColor: COLORS.SURFACE,
    borderRadius: 32,
    padding: 24,
    borderWidth: 1,
    borderColor: COLORS.OUTLINE_VARIANT,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 4,
    elevation: 1,
  },
  emptyInstructions: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 40,
    gap: 16,
  },
  tabHeader: {
    flexDirection: 'row',
    borderBottomWidth: 1,
    borderBottomColor: '#E7E8E9',
    marginBottom: 24,
  },
  tabButton: {
    paddingBottom: 12,
    paddingHorizontal: 16,
    marginRight: 16,
  },
  tabButtonActive: {
    borderBottomWidth: 2,
    borderBottomColor: COLORS.PRIMARY,
  },
  tabText: {
    fontSize: 14,
    fontWeight: '700',
    color: COLORS.OUTLINE,
  },
  tabTextActive: {
    color: COLORS.PRIMARY,
  },
  instructionContent: {
    gap: 20,
  },
  stepRow: {
    flexDirection: 'row',
    gap: 16,
  },
  stepBadge: {
    width: 32,
    height: 32,
    borderRadius: 16,
    backgroundColor: 'rgba(0, 109, 51, 0.1)',
    justifyContent: 'center',
    alignItems: 'center',
  },
  stepBadgeText: {
    color: COLORS.PRIMARY,
    fontWeight: '700',
    fontSize: 14,
  },
  stepTextContainer: {
    flex: 1,
  },
  stepTitle: {
    fontSize: 14,
    fontWeight: '700',
    color: COLORS.TEXT,
    marginBottom: 4,
  },
  stepDescription: {
    fontSize: 14,
    lineHeight: 20,
    color: COLORS.TEXT_VARIANT,
  },
  tipsContainer: {
    gap: 16,
  },
  tipItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    backgroundColor: '#F3F4F5',
    padding: 16,
    borderRadius: 16,
  },
  tipText: {
    flex: 1,
    fontSize: 14,
    color: COLORS.TEXT_VARIANT,
    lineHeight: 20,
  },
  footer: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    backgroundColor: 'rgba(255,255,255,0.8)',
    paddingHorizontal: 20,
    paddingTop: 16,
    gap: 12,
    borderTopWidth: 1,
    borderTopColor: '#EDEEEF',
  },
  primaryActionButton: {
    height: 64,
    borderRadius: 32,
    backgroundColor: COLORS.PRIMARY_CONTAINER,
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
    gap: 12,
    shadowColor: COLORS.PRIMARY_CONTAINER,
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.3,
    shadowRadius: 12,
    elevation: 4,
  },
  primaryActionButtonText: {
    color: COLORS.ON_PRIMARY_CONTAINER,
    fontWeight: '800',
    fontSize: 16,
    letterSpacing: 0.5,
  },
  secondaryActionButton: {
    height: 64,
    borderRadius: 32,
    borderWidth: 1,
    borderColor: COLORS.OUTLINE_VARIANT,
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
    gap: 8,
  },
  secondaryActionButtonText: {
    color: COLORS.TEXT,
    fontWeight: '700',
    fontSize: 14,
  },
  errorContainer: {
    flex: 1,
    backgroundColor: COLORS.BACKGROUND,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 32,
    gap: 24,
  },
  errorText: {
    color: COLORS.OUTLINE,
    fontSize: 16,
  },
  restContainer: {
    flex: 1,
    backgroundColor: COLORS.SURFACE,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 30,
  },
  restHeader: {
    alignItems: 'center',
    marginBottom: 40,
  },
  restLabel: {
    color: COLORS.OUTLINE,
    fontSize: 14,
    fontWeight: '800',
    letterSpacing: 2,
    marginBottom: 10,
  },
  restTimer: {
    color: COLORS.PRIMARY,
    fontSize: 80,
    fontWeight: '900',
  },
  upNextCard: {
    backgroundColor: COLORS.BACKGROUND,
    width: '100%',
    borderRadius: 32,
    padding: 24,
    alignItems: 'center',
    marginBottom: 40,
    borderWidth: 1,
    borderColor: COLORS.OUTLINE_VARIANT,
  },
  upNextLabel: {
    color: COLORS.OUTLINE,
    fontSize: 12,
    fontWeight: '800',
    letterSpacing: 1,
    marginBottom: 16,
  },
  upNextImage: {
    width: '100%',
    height: 180,
    borderRadius: 24,
    marginBottom: 20,
  },
  upNextTitle: {
    color: COLORS.TEXT,
    fontSize: 24,
    fontWeight: '800',
    textAlign: 'center',
    marginBottom: 8,
  },
  upNextDetails: {
    color: COLORS.PRIMARY,
    fontSize: 14,
    fontWeight: '700',
  },
  skipRestBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingVertical: 15,
    paddingHorizontal: 30,
    borderRadius: 32,
    borderWidth: 1,
    borderColor: COLORS.PRIMARY,
  },
  skipRestText: {
    color: COLORS.PRIMARY,
    fontSize: 14,
    fontWeight: '800',
    letterSpacing: 1,
  },
  finishBtn: {
    height: 48,
    borderRadius: 24,
    backgroundColor: COLORS.PRIMARY,
    paddingHorizontal: 24,
    justifyContent: 'center',
    alignItems: 'center',
    marginTop: 16,
  },
  finishBtnText: {
    color: COLORS.SURFACE,
    fontWeight: '700',
    fontSize: 14,
  },
  emptyText: {
    fontSize: 14,
    color: COLORS.OUTLINE,
    textAlign: 'center',
    lineHeight: 20,
    paddingHorizontal: 16,
  },
});

