import { SafeAreaView } from 'react-native-safe-area-context';
import { CustomAlert } from '../../../components/common/CustomAlert';
import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  ActivityIndicator,
  Alert
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { getWorkoutRecommendations, completeWorkout, WorkoutRecommendation, generateWorkoutDetails } from '../../../services/api/workoutService';
import { useNavigation, useRoute, RouteProp } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { RootStackParamList, MainTabParamList } from '../../../navigation/types';

type WorkoutScreenNavigationProp = NativeStackNavigationProp<RootStackParamList>;
type WorkoutScreenRouteProp = RouteProp<MainTabParamList, 'Workout'>;

export const WorkoutScreen = () => {
  const navigation = useNavigation<WorkoutScreenNavigationProp>();
  const route = useRoute<WorkoutScreenRouteProp>();
  const [workoutData, setWorkoutData] = useState<WorkoutRecommendation | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  
  const userId = route.params?.userId || 1;

  const handleToolPress = (toolId: string) => {
    switch (toolId) {
      case '1':
        navigation.navigate('FormCheck' as any);
        break;
      case '3':
        navigation.navigate('Achievements' as any);
        break;
      case '4':
        navigation.navigate('RoutineLibrary' as any);
        break;
      default:
        // 'Plan My Session' could re-trigger loadWorkoutData or show a modal
        loadWorkoutData();
        break;
    }
  };

  useEffect(() => {
    loadWorkoutData();
  }, []);

  const loadWorkoutData = async () => {
    try {
      setLoading(true);
      const data = await getWorkoutRecommendations(userId);
      setWorkoutData(data);
      setError(null);
    } catch (err) {
      // Error is already handled in service with fallback data
      console.error('Workout data error:', err);
    } finally {
      setLoading(false);
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
    return (
      <SafeAreaView style={styles.container}>
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#10B981" />
          <Text style={styles.loadingText}>Loading your workout plan...</Text>
        </View>
      </SafeAreaView>
    );
  }

  if (error || !workoutData) {
    return (
      <SafeAreaView style={styles.container}>
        <View style={styles.errorContainer}>
          <Ionicons name="alert-circle" size={48} color="#EF4444" />
          <Text style={styles.errorText}>{error || 'Unable to load workout data'}</Text>
          <TouchableOpacity style={styles.retryButton} onPress={loadWorkoutData}>
            <Text style={styles.retryButtonText}>Retry</Text>
          </TouchableOpacity>
        </View>
      </SafeAreaView>
    );
  }

  const aiTools = [
    { id: '1', title: 'Check Form with AI', icon: 'camera-outline' },
    { id: '2', title: 'Plan My Session', icon: 'calendar-outline' },
    { id: '3', title: 'View Past PRs', icon: 'trophy-outline' },
    { id: '4', title: 'Find a Routine', icon: 'search-outline' },
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
    <SafeAreaView style={styles.container}>
      <ScrollView
        style={styles.scrollView}
        contentContainerStyle={styles.scrollContent}
        showsVerticalScrollIndicator={false}
      >
        {/* Header Status */}
        <View style={styles.statusContainer}>
          <View style={styles.statusBadge}>
            <View style={styles.statusDot} />
            <Text style={styles.statusText}>{workoutData.status}</Text>
          </View>
          <View style={styles.recoveryBadge}>
            <Ionicons name="flash" size={14} color="#10B981" />
            <Text style={styles.recoveryText}>Ready</Text>
          </View>
        </View>

        {workoutData.todays_workout ? (
          <View style={styles.programCard}>
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
        ) : (
          <View style={styles.restDayCard}>
            <Ionicons name="bed-outline" size={60} color="#10B981" />
            <Text style={styles.restDayTitle}>Rest Day</Text>
            <Text style={styles.restDayText}>Recovery is just as important as training</Text>
          </View>
        )}

        {/* Weekly Goal */}
        <TouchableOpacity 
          style={styles.weeklyGoalCard}
          onPress={() => navigation.navigate('Schedule' as any)}
        >
          <View style={styles.weeklyGoalHeader}>
            <Text style={styles.weeklyGoalLabel}>WEEKLY GOAL</Text>
            <Text style={styles.weeklyGoalProgress}>
              {workoutData.weekly_progress.completed}/{workoutData.weekly_progress.goal}
            </Text>
          </View>
          <View style={styles.progressBarContainer}>
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
            <Text style={styles.sectionTitle}>AI Tools</Text>
          </View>
          <View style={styles.toolsGrid}>
            {aiTools.map((tool) => (
              <TouchableOpacity 
                key={tool.id} 
                style={styles.toolCard}
                onPress={() => handleToolPress(tool.id)}
              >
                <View style={styles.toolIcon}>
                  <Ionicons name={tool.icon as any} size={24} color="#10B981" />
                </View>
                <Text style={styles.toolTitle}>{tool.title}</Text>
              </TouchableOpacity>
            ))}
          </View>
        </View>

        {/* Upcoming Workouts */}
        <View style={styles.section}>
          <View style={styles.sectionHeaderRow}>
            <Text style={styles.sectionTitle}>Upcoming Workouts</Text>
            <TouchableOpacity onPress={() => navigation.navigate('Schedule' as any)}>
              <Text style={styles.viewScheduleText}>View Schedule</Text>
            </TouchableOpacity>
          </View>
          <View style={styles.workoutsContainer}>
            {(workoutData.upcoming_workouts || []).map((workout, index) => (
              <TouchableOpacity 
                key={index} 
                style={styles.workoutCard}
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
                  <View style={styles.workoutIconContainer}>
                    <Ionicons name="calendar-outline" size={24} color="#6B7280" />
                  </View>
                  <View style={styles.workoutInfo}>
                    <Text style={styles.workoutDate}>
                      {workout.scheduled_date ? formatDate(workout.scheduled_date) : 'TBD'}
                    </Text>
                    <Text style={styles.workoutTitle}>{workout.name}</Text>
                    <Text style={styles.workoutDetails}>
                      {workout.exercises_count} Exercises • {workout.duration}m
                    </Text>
                  </View>
                </View>
                <View style={styles.workoutMenu}>
                  <Ionicons name="ellipsis-horizontal" size={20} color="#6B7280" />
                </View>
              </TouchableOpacity>
            ))}
          </View>
        </View>

        <View style={styles.bottomSpacer} />
      </ScrollView>
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
    gap: 16,
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
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 8,
    elevation: 2,
  },
  restDayTitle: {
    fontSize: 24,
    fontWeight: 'bold',
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
    backgroundColor: '#1E293B',
    borderRadius: 24,
    padding: 24,
    marginBottom: 16,
    alignItems: 'center',
  },
  programImageContainer: {
    marginBottom: 16,
  },
  programImage: {
    width: 120,
    height: 120,
    borderRadius: 60,
    backgroundColor: '#374151',
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
    fontSize: 32,
    fontWeight: 'bold',
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
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 8,
    elevation: 2,
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
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 8,
    elevation: 2,
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
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 8,
    elevation: 2,
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
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 8,
    elevation: 2,
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
