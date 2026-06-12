import { SafeAreaView } from 'react-native-safe-area-context';
import React, { useState } from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { View, Text, StyleSheet, ScrollView, ActivityIndicator, Image, TouchableOpacity, Modal, TextInput, KeyboardAvoidingView, Platform, Alert } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { theme } from '../../../theme';
import { useRoute, RouteProp, useFocusEffect, useNavigation } from '@react-navigation/native';
import { MainTabParamList } from '../../../navigation/types';
import { getDashboardData, DashboardData, logWeight } from '../../../services/api/dashboardService';
import { notificationService, Notification } from '../../../services/api/notificationService';
import { AICoachModal } from '../../../components/common/AICoachModal';

type DashboardRouteProp = RouteProp<MainTabParamList, 'Home'>;

export const DashboardScreen = () => {
  const route = useRoute<DashboardRouteProp>();
  const navigation = useNavigation<any>();
  const [dashboardData, setDashboardData] = useState<DashboardData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showWeightModal, setShowWeightModal] = useState(false);
  const [weightInput, setWeightInput] = useState('');
  const [loggingWeight, setLoggingWeight] = useState(false);

  // Notification states
  const [unreadCount, setUnreadCount] = useState(0);
  const [latestNotification, setLatestNotification] = useState<Notification | null>(null);
  const [showAIModal, setShowAIModal] = useState(false);
  const [darkTheme, setDarkTheme] = useState(false);
  
  // Get userId from route params or use default (you should pass this from login)
  const userId = route.params?.userId || 1; // TODO: Get from auth context

  // Dynamic theme palette
  const colors = {
    background: darkTheme ? '#0F172A' : '#F9FAFB',
    cardBg:     darkTheme ? '#1E293B' : '#FFFFFF',
    text:       darkTheme ? '#F8FAFC' : '#1F2937',
    textSecondary: darkTheme ? '#94A3B8' : '#6B7280',
    border:     darkTheme ? '#334155' : '#E5E7EB',
    surface:    darkTheme ? '#1E293B' : '#FFFFFF',
    barEmpty:   darkTheme ? 'rgba(51,65,85,0.6)' : 'rgba(229,231,235,0.3)',
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
        } catch (e) {}
      })();
      loadDashboardData();
      fetchNotifications();
    }, [userId])
  );

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
  
  const loadDashboardData = async () => {
    try {
      setLoading(true);
      setError(null);
      const data = await getDashboardData(userId);
      setDashboardData(data);
    } catch (err) {
      setError('Failed to load dashboard data');
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
  
  if (loading) {
    return (
      <SafeAreaView style={[styles.container, { backgroundColor: colors.background }]}>
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color={theme.colors.primary} />
          <Text style={[styles.loadingText, { color: colors.textSecondary }]}>Loading your dashboard...</Text>
          <Text style={{ fontSize: 12, color: colors.textSecondary, marginTop: 8, textAlign: 'center' }}>
            If this takes too long, check your network connection.
          </Text>
        </View>
      </SafeAreaView>
    );
  }
  
  if (error || !dashboardData) {
    return (
      <SafeAreaView style={[styles.container, { backgroundColor: colors.background }]}>
        <View style={styles.errorContainer}>
          <Ionicons name="alert-circle" size={48} color={theme.colors.error} />
          <Text style={[styles.errorText, { color: colors.textSecondary }]}>{error || 'Unable to load data'}</Text>
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
              <View style={styles.weightLogBtn}>
                <View style={styles.weightValueRow}>
                  <Text style={[styles.trendValue, { color: colors.text }]}>
                    {currentWeight > 0 ? currentWeight.toFixed(1) : '--'}
                    <Text style={[styles.trendUnit, { color: colors.textSecondary }]}> kg</Text>
                  </Text>
                </View>
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
                    const dateStr = date.toISOString().split('T')[0];
                    const entry = weightHistory.find(w => w.recorded_date === dateStr);
                    last7Days.push({
                      date: dateStr,
                      dayName: ['SUN','MON','TUE','WED','THU','FRI','SAT'][date.getDay()],
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

      </ScrollView>
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.background,
    marginBottom:theme.spacing.xxl, 
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
});
