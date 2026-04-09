import React from 'react';
import {
  View,
  Text,
  StyleSheet,
  SafeAreaView,
  ScrollView,
  TouchableOpacity,
  Image,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';

export const WorkoutScreen = () => {
  const aiTools = [
    { id: '1', title: 'Check Form with AI', icon: 'camera-outline' },
    { id: '2', title: 'Plan My Session', icon: 'calendar-outline' },
    { id: '3', title: 'View Past PRs', icon: 'trophy-outline' },
    { id: '4', title: 'Find a Routine', icon: 'search-outline' },
  ];

  const upcomingWorkouts = [
    {
      id: '1',
      date: 'TOMORROW • 08:00 AM',
      title: 'Pull Day B',
      exercises: '8 Exercises',
      duration: '65m',
    },
    {
      id: '2',
      date: 'FRI, OCT 24',
      title: 'Leg Day',
      exercises: '5 Exercises',
      duration: '45m',
    },
  ];

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
            <Text style={styles.statusText}>READY FOR SESSION</Text>
          </View>
          <View style={styles.recoveryBadge}>
            <Ionicons name="flash" size={16} color="#10B981" />
            <Text style={styles.recoveryText}>98% Recovery</Text>
          </View>
        </View>

        {/* Today's Program Card */}
        <View style={styles.programCard}>
          <View style={styles.programImageContainer}>
            <View style={styles.programImage}>
              <Ionicons name="barbell" size={80} color="#10B981" />
            </View>
          </View>
          <Text style={styles.programLabel}>TODAY'S PROGRAM</Text>
          <Text style={styles.programTitle}>Push Day A</Text>
          <View style={styles.programDetails}>
            <View style={styles.programDetailItem}>
              <Ionicons name="fitness-outline" size={18} color="#10B981" />
              <Text style={styles.programDetailText}>6 exercises</Text>
            </View>
            <View style={styles.programDetailItem}>
              <Ionicons name="time-outline" size={18} color="#10B981" />
              <Text style={styles.programDetailText}>~55 mins</Text>
            </View>
          </View>
          <TouchableOpacity style={styles.startButton}>
            <Text style={styles.startButtonText}>START WORKOUT</Text>
            <Ionicons name="play" size={20} color="#FFFFFF" />
          </TouchableOpacity>
        </View>

        {/* New Achievement */}
        <TouchableOpacity style={styles.achievementCard}>
          <View style={styles.achievementLeft}>
            <View style={styles.achievementIcon}>
              <Ionicons name="trophy" size={24} color="#10B981" />
            </View>
            <View>
              <Text style={styles.achievementLabel}>NEW ACHIEVEMENT</Text>
              <Text style={styles.achievementTitle}>
                New Squat PR: <Text style={styles.achievementValue}>100kg</Text>
              </Text>
            </View>
          </View>
          <Ionicons name="chevron-forward" size={24} color="#6B7280" />
        </TouchableOpacity>

        {/* Weekly Goal */}
        <View style={styles.weeklyGoalCard}>
          <View style={styles.weeklyGoalHeader}>
            <Text style={styles.weeklyGoalLabel}>WEEKLY GOAL</Text>
            <Text style={styles.weeklyGoalProgress}>3/4</Text>
          </View>
          <View style={styles.progressBarContainer}>
            <View style={[styles.progressBar, { width: '75%' }]} />
          </View>
        </View>

        {/* AI Tools */}
        <View style={styles.section}>
          <View style={styles.sectionHeader}>
            <Ionicons name="sparkles" size={20} color="#10B981" />
            <Text style={styles.sectionTitle}>AI Tools</Text>
          </View>
          <View style={styles.toolsGrid}>
            {aiTools.map((tool) => (
              <TouchableOpacity key={tool.id} style={styles.toolCard}>
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
            <TouchableOpacity>
              <Text style={styles.viewScheduleText}>View Schedule</Text>
            </TouchableOpacity>
          </View>
          <View style={styles.workoutsContainer}>
            {upcomingWorkouts.map((workout) => (
              <TouchableOpacity key={workout.id} style={styles.workoutCard}>
                <View style={styles.workoutLeft}>
                  <View style={styles.workoutIconContainer}>
                    <Ionicons name="calendar-outline" size={24} color="#6B7280" />
                  </View>
                  <View style={styles.workoutInfo}>
                    <Text style={styles.workoutDate}>{workout.date}</Text>
                    <Text style={styles.workoutTitle}>{workout.title}</Text>
                    <Text style={styles.workoutDetails}>
                      {workout.exercises} • {workout.duration}
                    </Text>
                  </View>
                </View>
                <TouchableOpacity style={styles.workoutMenu}>
                  <Ionicons name="ellipsis-horizontal" size={20} color="#6B7280" />
                </TouchableOpacity>
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
