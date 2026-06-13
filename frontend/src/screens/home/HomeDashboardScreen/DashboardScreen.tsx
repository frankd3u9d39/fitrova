import { SafeAreaView } from 'react-native-safe-area-context';
import React, { useState, useEffect, useRef } from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { View, Text, StyleSheet, ScrollView, ActivityIndicator, Image, TouchableOpacity, Modal, TextInput, KeyboardAvoidingView, Platform, Alert, ImageBackground, Animated } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { theme } from '../../../theme';
import { useRoute, RouteProp, useFocusEffect, useNavigation } from '@react-navigation/native';
import { MainTabParamList } from '../../../navigation/types';
import { getDashboardData, DashboardData, logWeight, joinChallenge } from '../../../services/api/dashboardService';
import { notificationService, Notification } from '../../../services/api/notificationService';
import { AICoachModal } from '../../../components/common/AICoachModal';
import LottieView from 'lottie-react-native';

type DashboardRouteProp = RouteProp<MainTabParamList, 'Home'>;

const workoutImages = [
  require('../../../../assets/workout_athletes.png'),
  require('../../../../assets/workout_athlete_girl.png'),
  require('../../../../assets/workout_athlete_boy.png'),
];

export const DashboardScreen = () => {
  const route = useRoute<DashboardRouteProp>();
  const navigation = useNavigation<any>();
  const [dashboardData, setDashboardData] = useState<DashboardData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showWeightModal, setShowWeightModal] = useState(false);
  const [weightInput, setWeightInput] = useState('');
  const [loggingWeight, setLoggingWeight] = useState(false);
  const [joiningChallengeKey, setJoiningChallengeKey] = useState<string | null>(null);
  const [showSuccessAnimation, setShowSuccessAnimation] = useState(false);

  // Notification states
  const [unreadCount, setUnreadCount] = useState(0);
  const [latestNotification, setLatestNotification] = useState<Notification | null>(null);
  const [showAIModal, setShowAIModal] = useState(false);
  const [showAIPromptModal, setShowAIPromptModal] = useState(false);
  const [darkTheme, setDarkTheme] = useState(false);
  const [currentWorkoutImageIndex, setCurrentWorkoutImageIndex] = useState(0);
  const fadeAnim = useRef(new Animated.Value(1)).current;

  useEffect(() => {
    const interval = setInterval(() => {
      // 1. Fade out smoothly
      Animated.timing(fadeAnim, {
        toValue: 0,
        duration: 1000,
        useNativeDriver: true,
      }).start(() => {
        // 2. Switch image source
        setCurrentWorkoutImageIndex((prev) => (prev + 1) % 3);
        // 3. Fade back in smoothly
        Animated.timing(fadeAnim, {
          toValue: 1,
          duration: 1000,
          useNativeDriver: true,
        }).start();
      });
    }, 10000); // 10 seconds interval (calmer transition)

    return () => clearInterval(interval);
  }, [fadeAnim]);

  // Get userId from route params or use default (you should pass this from login)
  const userId = route.params?.userId || 1; // TODO: Get from auth context

  // Dynamic theme palette
  const colors = {
    background: darkTheme ? '#0F172A' : '#F9FAFB',
    cardBg: darkTheme ? '#1E293B' : '#FFFFFF',
    text: darkTheme ? '#F8FAFC' : '#1F2937',
    textSecondary: darkTheme ? '#94A3B8' : '#6B7280',
    border: darkTheme ? '#334155' : '#E5E7EB',
    surface: darkTheme ? '#1E293B' : '#FFFFFF',
    barEmpty: darkTheme ? 'rgba(51,65,85,0.6)' : 'rgba(229,231,235,0.3)',
  };

  const getGreeting = () => {
    const hour = new Date().getHours();
    if (hour < 12) return 'Good Morning';
    if (hour < 18) return 'Good Afternoon';
    return 'Good Evening';
  };

  useFocusEffect(
    React.useCallback(() => {
      // Load dark mode preference
      (async () => {
        try {
          const saved = await AsyncStorage.getItem(`user_prefs_${userId}`);
          if (saved) {
            const prefs = JSON.parse(saved);
            if (prefs.darkTheme !== undefined) setDarkTheme(prefs.darkTheme);
          }
        } catch (e) { }
      })();
      loadDashboardData();
      fetchNotifications();
    }, [userId])
  );

  useEffect(() => {
    let timer: any;

    const checkDismissalAndSchedule = async () => {
      if (!loading && dashboardData) {
        try {
          const dismissedVal = await AsyncStorage.getItem(`weight_prompt_dismissed_${userId}`);
          if (dismissedVal) {
            const dismissedDate = new Date(dismissedVal);
            const now = new Date();
            const diffTime = Math.abs(now.getTime() - dismissedDate.getTime());
            const diffDays = diffTime / (1000 * 60 * 60 * 24);
            if (diffDays < 4) {
              return;
            }
          }
        } catch (e) {
          console.error('Error reading weight prompt dismissal status:', e);
        }

        const today = new Date();
        const year = today.getFullYear();
        const month = String(today.getMonth() + 1).padStart(2, '0');
        const day = String(today.getDate()).padStart(2, '0');
        const todayDateStr = `${year}-${month}-${day}`;

        const loggedToday = dashboardData.weight.history.some(w => w.recorded_date === todayDateStr);

        if (!loggedToday) {
          timer = setTimeout(() => {
            setShowAIPromptModal(true);
          }, 7000);
        }
      }
    };

    checkDismissalAndSchedule();

    return () => {
      if (timer) clearTimeout(timer);
    };
  }, [loading, dashboardData, userId]);

  const fetchNotifications = async () => {
    try {
      const data = await notificationService.getNotifications(userId);
      setUnreadCount(data.unreadCount);
      const unreads = data.notifications.filter(n => !n.is_read);
      if (unreads.length > 0) {
        setLatestNotification(unreads[0]);
        setShowAIModal(true);
      }
    } catch (error) {
      console.error('Error fetching notifications:', error);
    }
  };

  const handleDismissAIModal = async () => {
    setShowAIModal(false);
    if (latestNotification) {
      await notificationService.markAsRead(userId, latestNotification.id);
      setLatestNotification(null);
      // Refresh count
      const data = await notificationService.getNotifications(userId);
      setUnreadCount(data.unreadCount);
    }
  };

  const handleDismissPrompt = async () => {
    setShowAIPromptModal(false);
    try {
      await AsyncStorage.setItem(`weight_prompt_dismissed_${userId}`, new Date().toISOString());
    } catch (e) {
      console.error('Error saving weight prompt dismissal status:', e);
    }
  };

  const loadDashboardData = async () => {
    try {
      setLoading(true);
      setError(null);
      const data = await getDashboardData(userId);
      setDashboardData(data);
    } catch (err: any) {
      let msg = err?.message || 'Failed to load dashboard data';
      if (msg.includes('Network request failed') || msg.includes('Failed to fetch') || msg.includes('AbortError') || msg.includes('timed out')) {
        msg = 'Network connection failed. Please check your internet connection and try again.';
      }
      setError(msg);
      console.error('Dashboard error:', err);
    } finally {
      setLoading(false);
    }
  };

  const handleLogWeight = async () => {
    const w = parseFloat(weightInput);
    if (isNaN(w) || w < 20 || w > 500) {
      Alert.alert('Invalid Weight', 'Please enter a valid weight (20–500 kg).');
      return;
    }
    try {
      setLoggingWeight(true);
      await logWeight(userId, w);
      setShowWeightModal(false);
      setWeightInput('');
      await loadDashboardData(); // refresh chart
    } catch (err: any) {
      Alert.alert('Error', err.message || 'Could not log weight.');
    } finally {
      setLoggingWeight(false);
    }
  };

  const handleJoinChallenge = async (challengeKey: string, currentJoined: boolean) => {
    try {
      setJoiningChallengeKey(challengeKey);
      const action = currentJoined ? 'leave' : 'join';
      await joinChallenge(userId, challengeKey, action);

      // Update local state to feel snappy
      if (dashboardData) {
        const updatedChallenges = dashboardData.challenges.map((c) => {
          if (c.key === challengeKey) {
            const countOffset = currentJoined ? -1 : 1;
            const updatedJoined = !currentJoined;

            // Recompute mock participants slice for me
            let updatedParticipants = [...c.participants];
            if (updatedJoined) {
              const myInitial = ((firstName?.[0] || 'Y') + (lastName?.[0] || '')).toUpperCase() || 'U';
              updatedParticipants.unshift({
                first_name: firstName || 'You',
                last_name: lastName || '',
                initials: myInitial,
                color: '#10B981',
                is_me: true
              });
              if (updatedParticipants.length > 3) {
                updatedParticipants.pop();
              }
            } else {
              updatedParticipants = updatedParticipants.filter(p => !p.is_me);
            }

            return {
              ...c,
              joined: updatedJoined,
              participants_count: c.participants_count + countOffset,
              participants: updatedParticipants,
            };
          }
          return c;
        });
        setDashboardData({
          ...dashboardData,
          challenges: updatedChallenges,
        });
      }

      if (action === 'join') {
        setShowSuccessAnimation(true);
        setTimeout(() => {
          setShowSuccessAnimation(false);
        }, 2000);
      }
    } catch (e: any) {
      Alert.alert('Error', e.message || 'Could not update challenge.');
    } finally {
      setJoiningChallengeKey(null);
    }
  };

  if (loading) {
    return (
      <SafeAreaView style={[styles.container, { backgroundColor: colors.background }]}>
        <View style={styles.loadingContainer}>
          <LottieView
            source={require('../../../../assets/animations/watermelon.json')}
            autoPlay
            loop
            style={{ width: 120, height: 120 }}
          />
        </View>
      </SafeAreaView>
    );
  }

  if (error || !dashboardData) {
    return (
      <SafeAreaView style={[styles.container, { backgroundColor: colors.background }]}>
        <View style={styles.errorContainer}>
          <LottieView
            source={require('../../../../assets/animations/PinJump.json')}
            autoPlay
            loop
            style={{ width: 140, height: 140, marginBottom: 16 }}
          />
          <Text style={[styles.errorText, { color: colors.textSecondary, fontWeight: '600', fontSize: 16 }]}>
            {error || 'No dashboard data available.'}
          </Text>
          <TouchableOpacity
            onPress={loadDashboardData}
            style={{ marginTop: 16, paddingHorizontal: 24, paddingVertical: 12, backgroundColor: theme.colors.primary, borderRadius: 12 }}
          >
            <Text style={{ color: '#fff', fontWeight: '700', fontSize: 15 }}>Retry</Text>
          </TouchableOpacity>
        </View>
      </SafeAreaView>
    );
  }


  const firstName = dashboardData.user.first_name;
  const lastName = dashboardData.user.last_name;
  const profilePicture = dashboardData.user.profile_picture;
  const healthScore = dashboardData.health_score;
  const caloriesConsumed = dashboardData.calories.consumed;
  const caloriesGoal = dashboardData.calories.goal;
  const currentWeight = dashboardData.weight.current || 0;
  const weightHistory = dashboardData.weight.history;
  const todayWorkout = dashboardData.today_workout;
  const insight = dashboardData.insight;

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: colors.background }]}>
      <ScrollView contentContainerStyle={styles.scrollContent} showsVerticalScrollIndicator={false}>

        {/* Header Section */}
        <View style={styles.header}>
          <View style={styles.headerTextContainer}>
            <Text style={[styles.greeting, { color: colors.text }]}>{getGreeting()},<Text style={styles.nameHighlight}>{firstName}</Text></Text>
            <Text style={[styles.subtitle, { color: colors.textSecondary }]}>{"Ready to crush your goals today?"}</Text>
          </View>
          <View style={styles.headerRight}>
            <TouchableOpacity
              style={[styles.bellButton, { backgroundColor: colors.cardBg, borderColor: colors.border }]}
              onPress={() => navigation.navigate('Notifications', { userId })}
              activeOpacity={0.7}
            >
              <Ionicons name="notifications-outline" size={24} color={colors.text} />
              {unreadCount > 0 && (
                <View style={styles.badgeContainer}>
                  <Text style={styles.badgeText}>{unreadCount}</Text>
                </View>
              )}
            </TouchableOpacity>
            <View style={styles.headerAvatar}>
              {profilePicture ? (
                <Image
                  source={{ uri: profilePicture }}
                  style={styles.headerAvatarImage}
                />
              ) : (
                <Text style={styles.headerAvatarText}>
                  {((firstName?.[0] || '') + (lastName?.[0] || '')).toUpperCase() || 'U'}
                </Text>
              )}
            </View>
          </View>
        </View>

        {/* Stats Row */}
        <View style={styles.statsRow}>

          {/* AI Health Score Card */}
          <View style={[styles.statCard, styles.healthCard, { backgroundColor: colors.cardBg }]}>
            <View style={styles.cardHeaderRow}>
              <Text style={[styles.cardTitle, { color: colors.textSecondary }]}>AI HEALTH SCORE</Text>
              <Ionicons name="sparkles" size={14} color={theme.colors.primary} />
            </View>
            <View style={styles.healthScoreContent}>
              <View style={styles.scoreCircle}>
                <Text style={[styles.scoreNumber, { color: colors.text }]}>{healthScore}</Text>
              </View>
              <View style={styles.scoreDetails}>
                <Text style={styles.scoreChange}>↗ +5 pts</Text>
                <Text style={[styles.scoreSubtext, { color: colors.textSecondary }]}>Out of 100</Text>
              </View>
            </View>
          </View>

          {/* Calories Card */}
          <View style={[styles.statCard, styles.caloriesCard, { backgroundColor: colors.cardBg }]}>
            <Text style={[styles.cardTitle, { color: colors.textSecondary }]}>CALORIES</Text>
            <View style={styles.caloriesContent}>
              <View style={styles.fireIconContainer}>
                <Ionicons name="flame" size={20} color="#FF6B35" />
              </View>
              <Text style={[styles.calorieValue, { color: colors.text }]}>{caloriesConsumed.toLocaleString()}</Text>
              <Text style={[styles.calorieTarget, { color: colors.textSecondary }]}>/ {caloriesGoal.toLocaleString()}</Text>
            </View>
          </View>

        </View>

        {/* Today's Workout Hero */}
        <View style={styles.workoutCard}>
          <Animated.Image
            source={workoutImages[currentWorkoutImageIndex]}
            style={[
              StyleSheet.absoluteFillObject,
              { opacity: fadeAnim, borderRadius: theme.borderRadius.xl }
            ]}
            resizeMode="cover"
          />
          <View style={styles.workoutCardOverlay}>
            <Text style={styles.workoutSubtitle}>TODAY'S WORKOUT</Text>
            {todayWorkout ? (
              <>
                <Text style={styles.workoutTitle}>{todayWorkout.name}</Text>
                <View style={styles.durationBadge}>
                  <Ionicons name="time-outline" size={12} color="#fff" />
                  <Text style={styles.durationText}> {todayWorkout.duration} min</Text>
                </View>
              </>
            ) : (
              <Text style={styles.workoutTitle}>No workout{'\n'}scheduled</Text>
            )}
          </View>
        </View>

        {/* Weight Trend */}
        <View style={styles.trendSection}>
          <View style={styles.trendHeader}>
            <Text style={[styles.trendTitle, { color: colors.textSecondary }]}>WEIGHT TREND</Text>
            <View>
              <Text style={[styles.trendValue, { color: colors.text }]}>
                {currentWeight > 0 ? currentWeight.toFixed(1) : '--'}
                <Text style={[styles.trendUnit, { color: colors.textSecondary }]}> kg</Text>
              </Text>
            </View>
          </View>

          <View style={[styles.chartContainer, { backgroundColor: colors.cardBg }]}>
            <View style={styles.chartBars}>
              {(() => {
                const last7Days = [];
                const today = new Date();
                for (let i = 6; i >= 0; i--) {
                  const date = new Date(today);
                  date.setDate(date.getDate() - i);
                  const year = date.getFullYear();
                  const month = String(date.getMonth() + 1).padStart(2, '0');
                  const day = String(date.getDate()).padStart(2, '0');
                  const dateStr = `${year}-${month}-${day}`;
                  const entry = weightHistory.find(w => w.recorded_date === dateStr);
                  last7Days.push({
                    date: dateStr,
                    dayName: ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'][date.getDay()],
                    weight: entry ? Number(entry.weight) : null,
                    isToday: i === 0,
                  });
                }
                const weights = last7Days.filter(d => d.weight !== null).map(d => d.weight!);
                const maxWeight = weights.length > 0 ? Math.max(...weights) : 100;
                const minWeight = weights.length > 0 ? Math.min(...weights) : 90;
                const range = maxWeight - minWeight || 5;
                return last7Days.map((day, index) => {
                  const h = day.weight
                    ? ((day.weight - minWeight) / range) * 70 + 30
                    : 15;
                  return (
                    <View key={index} style={styles.barWrapper}>
                      <View style={styles.barContainer}>
                        <View style={[
                          styles.barFill,
                          { backgroundColor: colors.border },
                          day.isToday && styles.barFillActive,
                          !day.weight && { backgroundColor: colors.barEmpty },
                          { height: `${h}%` }
                        ]} />
                      </View>
                      <Text style={[styles.chartLabel, { color: colors.textSecondary }, day.isToday && styles.chartLabelActive]}>{day.dayName}</Text>
                    </View>
                  );
                });
              })()}
            </View>
          </View>
        </View>

        {/* Personalized AI Challenges Section */}
        <View style={styles.challengesSection}>
          <View style={styles.challengesHeader}>
            <Text style={[styles.challengesSectionTitle, { color: colors.textSecondary }]}>PERSONALIZED AI CHALLENGES</Text>
            <View style={styles.gemmaBadge}>
              <Ionicons name="sparkles" size={12} color={theme.colors.primary} />
              <Text style={styles.gemmaBadgeText}>Fitrova AI</Text>
            </View>
          </View>

          {dashboardData.challenges && dashboardData.challenges.length > 0 ? (
            dashboardData.challenges.map((challenge) => (
              <View
                key={challenge.key}
                style={[styles.challengeCard, { backgroundColor: colors.cardBg, borderColor: colors.border }]}
              >
                <View style={styles.challengeCardHeader}>
                  <View style={styles.challengeMeta}>
                    <Text style={[styles.challengeDifficulty, {
                      color: challenge.difficulty === 'Advanced' ? '#EF4444' : challenge.difficulty === 'Intermediate' ? '#F59E0B' : '#10B981',
                      backgroundColor: challenge.difficulty === 'Advanced' ? 'rgba(239, 68, 68, 0.1)' : challenge.difficulty === 'Intermediate' ? 'rgba(245, 158, 11, 0.1)' : 'rgba(16, 185, 129, 0.1)'
                    }]}>
                      {challenge.difficulty}
                    </Text>
                    <Text style={[styles.challengeDuration, { color: colors.textSecondary }]}>⏱ {challenge.duration}</Text>
                  </View>
                  <TouchableOpacity
                    style={[
                      styles.challengeJoinBtn,
                      challenge.joined && styles.challengeJoinedBtn,
                      joiningChallengeKey === challenge.key && { opacity: 0.7 }
                    ]}
                    onPress={() => handleJoinChallenge(challenge.key, challenge.joined)}
                    disabled={joiningChallengeKey !== null}
                    activeOpacity={0.8}
                  >
                    {joiningChallengeKey === challenge.key ? (
                      <ActivityIndicator size="small" color="#FFFFFF" />
                    ) : (
                      <Text style={[styles.challengeJoinBtnText, challenge.joined && styles.challengeJoinedBtnText]}>
                        {challenge.joined ? 'Joined' : 'Join'}
                      </Text>
                    )}
                  </TouchableOpacity>
                </View>

                <Text style={[styles.challengeTitle, { color: colors.text }]}>{challenge.title}</Text>
                <Text style={[styles.challengeDescription, { color: colors.textSecondary }]}>{challenge.description}</Text>

                {/* Participants row */}
                <View style={styles.challengeFooter}>
                  <View style={styles.participantAvatars}>
                    {challenge.participants.map((participant, index) => (
                      <View
                        key={index}
                        style={[
                          styles.participantAvatarCircle,
                          { backgroundColor: participant.profile_picture ? 'transparent' : participant.color, zIndex: 10 - index }
                        ]}
                      >
                        {participant.profile_picture ? (
                          <Image
                            source={{ uri: participant.profile_picture }}
                            style={styles.participantAvatarImage}
                          />
                        ) : (
                          <Text style={styles.participantAvatarText}>{participant.initials}</Text>
                        )}
                      </View>
                    ))}
                    {challenge.participants_count > challenge.participants.length && (
                      <Text style={[styles.othersText, { color: colors.textSecondary }]}>
                        +{challenge.participants_count - challenge.participants.length} others active
                      </Text>
                    )}
                  </View>
                  <View style={styles.participantCountRow}>
                    <Ionicons name="people" size={16} color={theme.colors.primary} />
                    <Text style={[styles.participantCountText, { color: colors.text }]}>{challenge.participants_count} joined</Text>
                  </View>
                </View>
              </View>
            ))
          ) : (
            <View style={[styles.emptyStateContainer, { backgroundColor: colors.cardBg, borderColor: colors.border }]}>
              <Text style={{ color: colors.textSecondary }}>No active challenges at this time.</Text>
            </View>
          )}
        </View>

        {/* Weight Log Modal */}
        <Modal visible={showWeightModal} animationType="slide" presentationStyle="pageSheet" onRequestClose={() => setShowWeightModal(false)}>
          <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
            <SafeAreaView style={[styles.weightModalContainer, { backgroundColor: colors.cardBg }]}>
              <View style={[styles.weightModalHeader, { borderBottomColor: colors.border }]}>
                <Text style={[styles.weightModalTitle, { color: colors.text }]}>Log Today's Weight</Text>
                <TouchableOpacity onPress={() => setShowWeightModal(false)}>
                  <Ionicons name="close" size={26} color={colors.textSecondary} />
                </TouchableOpacity>
              </View>
              <View style={styles.weightModalBody}>
                <Text style={[styles.weightModalSub, { color: colors.textSecondary }]}>Enter your current weight to update your trend chart.</Text>
                <View style={[styles.weightInputRow, { backgroundColor: darkTheme ? '#0F172A' : '#F9FAFB' }]}>
                  <TextInput
                    style={[styles.weightInput, { color: colors.text }]}
                    value={weightInput}
                    onChangeText={setWeightInput}
                    keyboardType="decimal-pad"
                    placeholder="e.g. 72.5"
                    placeholderTextColor={colors.textSecondary}
                    autoFocus
                    maxLength={6}
                  />
                  <Text style={[styles.weightInputUnit, { color: colors.textSecondary }]}>kg</Text>
                </View>
                <TouchableOpacity
                  style={[styles.weightSaveBtn, loggingWeight && { opacity: 0.6 }]}
                  onPress={handleLogWeight}
                  disabled={loggingWeight}
                >
                  <Text style={styles.weightSaveBtnText}>{loggingWeight ? 'Saving...' : 'Save Weight'}</Text>
                </TouchableOpacity>
              </View>
            </SafeAreaView>
          </KeyboardAvoidingView>
        </Modal>

        {/* AI pop-up Coach Modal */}
        <AICoachModal
          visible={showAIModal}
          notification={latestNotification}
          onDismiss={handleDismissAIModal}
        />

        {/* AI Prompt Modal for Weight Update */}
        <Modal
          transparent
          visible={showAIPromptModal}
          animationType="fade"
          onRequestClose={handleDismissPrompt}
        >
          <View style={styles.promptOverlay}>
            <View style={[styles.promptCard, { backgroundColor: colors.cardBg, borderColor: colors.border }]}>
              {/* Premium Icon Badge */}
              <View style={[styles.promptIconWrapper, { backgroundColor: darkTheme ? 'rgba(16, 185, 129, 0.1)' : '#ECFDF5', borderColor: darkTheme ? '#059669' : '#A7F3D0' }]}>
                <Ionicons name="sparkles" size={32} color="#10B981" />
              </View>

              {/* Content Container */}
              <View style={styles.promptContent}>
                <Text style={[styles.promptTag, { color: '#10B981' }]}>AI ENGINE ACCURACY</Text>
                <Text style={[styles.promptTitle, { color: colors.text }]}>Optimize AI Performance</Text>
                <Text style={[styles.promptBodyText, { color: colors.textSecondary }]}>
                  Have you checked your weight recently? Keeping your weight log up-to-date helps our smart engine generate highly accurate workout plans and balance parameters.
                </Text>
              </View>

              {/* Action Buttons */}
              <View style={styles.promptActions}>
                <TouchableOpacity
                  style={styles.promptPrimaryBtn}
                  onPress={() => {
                    setShowAIPromptModal(false);
                    setShowWeightModal(true);
                  }}
                  activeOpacity={0.85}
                >
                  <Text style={styles.promptPrimaryBtnText}>Log Weight Now</Text>
                  <Ionicons name="chevron-forward" size={16} color="#FFFFFF" />
                </TouchableOpacity>

                <TouchableOpacity
                  style={[styles.promptSecondaryBtn, { borderColor: colors.border }]}
                  onPress={handleDismissPrompt}
                  activeOpacity={0.85}
                >
                  <Text style={[styles.promptSecondaryBtnText, { color: colors.textSecondary }]}>Maybe Later</Text>
                </TouchableOpacity>
              </View>
            </View>
          </View>
        </Modal>

      </ScrollView>

      {showSuccessAnimation && (
        <View style={styles.animationOverlay}>
          <View style={[styles.animationCard, { backgroundColor: colors.cardBg }]}>
            <LottieView
              source={require('../../../../assets/animations/success.json')}
              autoPlay
              loop={false}
              style={styles.successLottie}
            />
            <Text style={[styles.successText, { color: colors.text }]}>Challenge Joined!</Text>
            <Text style={[styles.successSubtext, { color: colors.textSecondary }]}>Let's crush this goal together.</Text>
          </View>
        </View>
      )}
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.background,
    marginBottom: theme.spacing.xxl,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    gap: theme.spacing.md,
  },
  loadingText: {
    ...theme.typography.body,
    color: theme.colors.textSecondary,
  },
  errorContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    gap: theme.spacing.md,
    paddingHorizontal: theme.spacing.xl,
  },
  errorText: {
    ...theme.typography.body,
    color: theme.colors.textSecondary,
    textAlign: 'center',
  },
  scrollContent: {
    flexGrow: 1,
    paddingHorizontal: theme.spacing.lg,
    paddingTop: theme.spacing.xl,
    paddingBottom: theme.spacing.xl,

  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: theme.spacing.xl,
    marginTop: theme.spacing.xl,
  },
  headerTextContainer: {
    flex: 1,
    marginRight: theme.spacing.md,
  },
  headerAvatar: {
    width: 42,
    height: 42,
    borderRadius: 26,
    backgroundColor: '#ECFDF5',
    justifyContent: 'center',
    alignItems: 'center',
    borderWidth: 2.5,
    borderColor: theme.colors.primary,
    overflow: 'hidden',
    ...theme.shadows.sm,
    shadowColor: theme.colors.primary, // Keep the custom branded green glow
  },
  headerAvatarImage: {
    width: '100%',
    height: '100%',
    borderRadius: 26,
  },
  headerAvatarText: {
    fontSize: 16,
    fontWeight: '800',
    color: theme.colors.primary,
  },
  greeting: {
    ...theme.typography.h2,
    marginBottom: 4,
  },
  nameHighlight: {
    color: theme.colors.primary,
  },
  subtitle: {
    ...theme.typography.body,
    color: theme.colors.textSecondary,
  },
  statsRow: {
    flexDirection: 'row',
    gap: theme.spacing.md,
    marginBottom: theme.spacing.xl,
  },
  statCard: {
    backgroundColor: theme.colors.surface,
    borderRadius: theme.borderRadius.xl,
    padding: theme.spacing.lg,
    flex: 1,
    ...theme.shadows.md,
  },
  healthCard: {
    flex: 1.2, // slightly wider
  },
  caloriesCard: {
    flex: 0.8,
  },
  cardHeaderRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: theme.spacing.sm,
  },
  cardTitle: {
    fontSize: 10,
    fontWeight: '700',
    color: theme.colors.textSecondary,
    letterSpacing: 0.5,
  },
  healthScoreContent: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.spacing.md,
    marginTop: theme.spacing.sm,
  },
  scoreCircle: {
    width: 60,
    height: 60,
    borderRadius: 30,
    borderWidth: 4,
    borderColor: theme.colors.primary,
    justifyContent: 'center',
    alignItems: 'center',
  },
  scoreNumber: {
    ...theme.typography.h3,
    fontWeight: '800',
    color: theme.colors.text,
  },
  scoreDetails: {
    justifyContent: 'center',
  },
  scoreChange: {
    fontSize: 12,
    fontWeight: '700',
    color: theme.colors.primary,
    marginBottom: 2,
  },
  scoreSubtext: {
    fontSize: 10,
    color: theme.colors.textSecondary,
  },
  caloriesContent: {
    alignItems: 'center',
    marginTop: theme.spacing.md,
  },
  fireIconContainer: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: 'rgba(255, 107, 53, 0.1)', // Light orange tint
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: theme.spacing.sm,
  },
  calorieValue: {
    ...theme.typography.h3,
    fontWeight: '800',
  },
  calorieTarget: {
    fontSize: 12,
    color: theme.colors.textSecondary,
    marginTop: 2,
  },
  workoutCard: {
    height: 160,
    backgroundColor: '#1E2C26', // Dark green slate
    borderRadius: theme.borderRadius.xl,
    marginBottom: theme.spacing.xl,
    overflow: 'hidden',
    position: 'relative',
  },
  workoutCardOverlay: {
    flex: 1,
    padding: theme.spacing.lg,
    justifyContent: 'center',
    backgroundColor: 'rgba(15, 23, 42, 0.6)', // Premium semi-transparent overlay
  },
  workoutSubtitle: {
    fontSize: 10,
    fontWeight: '700',
    color: theme.colors.primary,
    letterSpacing: 1,
    marginBottom: theme.spacing.xs,
  },
  workoutTitle: {
    ...theme.typography.h2,
    color: '#fff',
    lineHeight: 32,
    marginBottom: theme.spacing.sm,
  },
  durationBadge: {
    position: 'absolute',
    bottom: theme.spacing.lg,
    right: theme.spacing.lg,
    backgroundColor: 'rgba(255, 255, 255, 0.2)',
    paddingHorizontal: theme.spacing.md,
    paddingVertical: theme.spacing.sm,
    borderRadius: theme.borderRadius.full,
    flexDirection: 'row',
    alignItems: 'center',
  },
  durationText: {
    fontSize: 12,
    fontWeight: '600',
    color: '#fff',
  },
  trendSection: {
    marginBottom: theme.spacing.xl,
  },
  trendHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: theme.spacing.md,
  },
  trendTitle: {
    fontSize: 12,
    fontWeight: '700',
    color: theme.colors.textSecondary,
    letterSpacing: 0.5,
  },
  trendValue: {
    ...theme.typography.h3,
  },
  trendUnit: {
    fontSize: 12,
    color: theme.colors.textSecondary,
  },
  chartContainer: {
    backgroundColor: theme.colors.surface,
    borderRadius: theme.borderRadius.xl,
    padding: theme.spacing.lg,
    paddingBottom: theme.spacing.md,
  },
  chartBars: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-end',
    height: 120,
    marginBottom: theme.spacing.sm,
  },
  barWrapper: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'flex-end',
    height: '100%',
  },
  barContainer: {
    width: '70%',
    height: '85%',
    justifyContent: 'flex-end',
    alignItems: 'center',
  },
  barFill: {
    width: '100%',
    backgroundColor: theme.colors.border,
    borderRadius: 4,
    minHeight: 8,
  },
  barFillActive: {
    backgroundColor: theme.colors.primary,
  },
  barFillEmpty: {
    backgroundColor: 'rgba(229, 231, 235, 0.3)',
  },
  chartLabels: {
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  chartLabel: {
    fontSize: 10,
    color: theme.colors.textSecondary,
    fontWeight: '600',
  },
  chartLabelActive: {
    color: theme.colors.primary,
    fontWeight: '800',
  },
  weightLogBtn: {
    alignItems: 'flex-end',
  },
  weightValueRow: {
    flexDirection: 'row',
    alignItems: 'baseline',
    gap: 4,
  },
  logWeightHint: {
    fontSize: 9,
    fontWeight: '700',
    color: theme.colors.primary,
    letterSpacing: 0.8,
    marginTop: 2,
  },
  deltaUp: {
    fontSize: 12,
    fontWeight: '700',
    color: '#EF4444',
  },
  deltaDown: {
    fontSize: 12,
    fontWeight: '700',
    color: '#10B981',
  },
  deltaFlat: {
    fontSize: 12,
    fontWeight: '700',
    color: '#9CA3AF',
  },
  barWeightLabel: {
    fontSize: 8,
    color: theme.colors.textSecondary,
    fontWeight: '600',
    marginBottom: 2,
  },
  // Weight modal styles
  weightModalContainer: {
    flex: 1,
    backgroundColor: '#FFFFFF',
  },
  weightModalHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 24,
    paddingVertical: 18,
    borderBottomWidth: 1,
    borderBottomColor: '#F3F4F6',
  },
  weightModalTitle: {
    fontSize: 20,
    fontWeight: '800',
    color: '#1F2937',
  },
  weightModalBody: {
    padding: 24,
    gap: 20,
  },
  weightModalSub: {
    fontSize: 14,
    color: '#6B7280',
    lineHeight: 20,
  },
  weightInputRow: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#F9FAFB',
    borderRadius: 16,
    borderWidth: 2,
    borderColor: theme.colors.primary,
    paddingHorizontal: 20,
    height: 70,
    gap: 8,
  },
  weightInput: {
    flex: 1,
    fontSize: 36,
    fontWeight: '800',
    color: '#1F2937',
  },
  weightInputUnit: {
    fontSize: 20,
    fontWeight: '600',
    color: '#6B7280',
  },
  weightSaveBtn: {
    backgroundColor: theme.colors.primary,
    paddingVertical: 16,
    borderRadius: 16,
    alignItems: 'center',
    justifyContent: 'center',
  },
  weightSaveBtnText: {
    color: '#FFFFFF',
    fontSize: 16,
    fontWeight: '700',
  },
  headerRight: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 16,
  },
  bellButton: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: '#FFFFFF',
    justifyContent: 'center',
    alignItems: 'center',
    borderWidth: 1,
    borderColor: '#E5E7EB',
    position: 'relative',
    ...theme.shadows.sm,
  },
  badgeContainer: {
    position: 'absolute',
    top: -4,
    right: -4,
    backgroundColor: '#EF4444',
    borderRadius: 10,
    width: 20,
    height: 20,
    justifyContent: 'center',
    alignItems: 'center',
    borderWidth: 1.5,
    borderColor: '#FFFFFF',
  },
  badgeText: {
    color: '#FFFFFF',
    fontSize: 9,
    fontWeight: '800',
  },
  insightBanner: {
    backgroundColor: 'rgba(0, 230, 0, 0.1)',
    borderRadius: theme.borderRadius.xl,
    padding: theme.spacing.lg,
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: theme.spacing.xl,
  },
  insightIconContainer: {
    width: 40,
    height: 40,
    backgroundColor: theme.colors.primary,
    borderRadius: 20,
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: theme.spacing.md,
  },
  insightTextContent: {
    flex: 1,
  },
  insightTitle: {
    fontSize: 10,
    fontWeight: '700',
    color: '#00cc00',
    letterSpacing: 0.5,
    marginBottom: 4,
  },
  insightBody: {
    ...theme.typography.bodySmall,
    color: theme.colors.text,
    lineHeight: 20,
  },
  insightHighlight: {
    fontWeight: '700',
    color: theme.colors.text,
  },
  // AI Prompt Modal Styles
  promptOverlay: {
    flex: 1,
    backgroundColor: 'rgba(15, 23, 42, 0.75)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 24,
  },
  promptCard: {
    width: '100%',
    maxWidth: 340,
    borderRadius: 28,
    borderWidth: 1.5,
    overflow: 'hidden',
    padding: 24,
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.15,
    shadowRadius: 25,
    elevation: 10,
  },
  promptIconWrapper: {
    width: 68,
    height: 68,
    borderRadius: 34,
    borderWidth: 2,
    justifyContent: 'center',
    alignItems: 'center',
    marginTop: 8,
    marginBottom: 16,
  },
  promptContent: {
    alignItems: 'center',
    marginBottom: 24,
    gap: 8,
  },
  promptTag: {
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 1.5,
  },
  promptTitle: {
    fontSize: 18,
    fontWeight: '800',
    textAlign: 'center',
  },
  promptBodyText: {
    fontSize: 14,
    textAlign: 'center',
    lineHeight: 20,
    marginTop: 4,
  },
  promptActions: {
    width: '100%',
    gap: 10,
  },
  promptPrimaryBtn: {
    height: 52,
    borderRadius: 16,
    backgroundColor: '#10B981',
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
    gap: 6,
    width: '100%',
  },
  promptPrimaryBtnText: {
    color: '#FFFFFF',
    fontSize: 15,
    fontWeight: '700',
  },
  promptSecondaryBtn: {
    height: 48,
    borderRadius: 16,
    borderWidth: 1.5,
    justifyContent: 'center',
    alignItems: 'center',
    width: '100%',
  },
  promptSecondaryBtnText: {
    fontSize: 14,
    fontWeight: '600',
  },
  // Challenges Styles
  challengesSection: {
    marginTop: theme.spacing.xl,
    marginBottom: theme.spacing.xl,
  },
  challengesHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: theme.spacing.md,
  },
  challengesSectionTitle: {
    fontSize: 12,
    fontWeight: '700',
    letterSpacing: 0.5,
  },
  gemmaBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: 'rgba(16, 185, 129, 0.1)',
    borderRadius: 8,
    paddingHorizontal: 8,
    paddingVertical: 4,
  },
  gemmaBadgeText: {
    fontSize: 10,
    fontWeight: '700',
    color: theme.colors.primary,
  },
  challengeCard: {
    borderRadius: theme.borderRadius.xl,
    padding: theme.spacing.lg,
    borderWidth: 1,
    marginBottom: theme.spacing.md,
    ...theme.shadows.md,
  },
  challengeCardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: theme.spacing.sm,
  },
  challengeMeta: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  challengeDifficulty: {
    fontSize: 10,
    fontWeight: '800',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 6,
    textTransform: 'uppercase',
  },
  challengeDuration: {
    fontSize: 11,
    fontWeight: '600',
  },
  challengeJoinBtn: {
    backgroundColor: theme.colors.primary,
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: theme.borderRadius.lg,
    minWidth: 70,
    alignItems: 'center',
    justifyContent: 'center',
  },
  challengeJoinedBtn: {
    backgroundColor: 'rgba(16, 185, 129, 0.15)',
    borderWidth: 1,
    borderColor: theme.colors.primary,
  },
  challengeJoinBtnText: {
    color: '#FFFFFF',
    fontSize: 12,
    fontWeight: '700',
  },
  challengeJoinedBtnText: {
    color: theme.colors.primary,
  },
  challengeTitle: {
    fontSize: 18,
    fontWeight: '800',
    marginBottom: 4,
  },
  challengeDescription: {
    fontSize: 13,
    lineHeight: 18,
    marginBottom: theme.spacing.md,
  },
  challengeFooter: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    borderTopWidth: 1,
    borderTopColor: 'rgba(156, 163, 175, 0.1)',
    paddingTop: theme.spacing.sm,
  },
  participantAvatars: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  participantAvatarCircle: {
    width: 24,
    height: 24,
    borderRadius: 12,
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: -8,
    borderWidth: 1.5,
    borderColor: '#FFFFFF',
  },
  participantAvatarText: {
    color: '#FFFFFF',
    fontSize: 9,
    fontWeight: '800',
  },
  participantAvatarImage: {
    width: '100%',
    height: '100%',
    borderRadius: 12,
  },
  othersText: {
    fontSize: 11,
    fontWeight: '600',
    marginLeft: 14,
  },
  participantCountRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
  },
  participantCountText: {
    fontSize: 12,
    fontWeight: '700',
  },
  emptyStateContainer: {
    borderRadius: theme.borderRadius.xl,
    padding: theme.spacing.xl,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
  },
  animationOverlay: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: 'rgba(15, 23, 42, 0.75)',
    justifyContent: 'center',
    alignItems: 'center',
    zIndex: 9999,
  },
  animationCard: {
    borderRadius: 24,
    padding: 32,
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.25,
    shadowRadius: 15,
    elevation: 10,
    width: '80%',
    maxWidth: 320,
  },
  successLottie: {
    width: 150,
    height: 150,
  },
  successText: {
    fontSize: 20,
    fontWeight: 'bold',
    marginTop: 16,
    textAlign: 'center',
  },
  successSubtext: {
    fontSize: 14,
    marginTop: 8,
    textAlign: 'center',
  },
});
