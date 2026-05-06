import { SafeAreaView } from 'react-native-safe-area-context';
import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet,  ScrollView, ActivityIndicator } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { theme } from '../../../theme';
import { useRoute, RouteProp } from '@react-navigation/native';
import { MainTabParamList } from '../../../navigation/AppNavigator';
import { getDashboardData, DashboardData } from '../../../services/api/dashboardService';

type DashboardRouteProp = RouteProp<MainTabParamList, 'Home'>;

export const DashboardScreen = () => {
  const route = useRoute<DashboardRouteProp>();
  const [dashboardData, setDashboardData] = useState<DashboardData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  
  // Get userId from route params or use default (you should pass this from login)
  const userId = route.params?.userId || 1; // TODO: Get from auth context
  
  useEffect(() => {
    loadDashboardData();
  }, [userId]);
  
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
  
  if (loading) {
    return (
      <SafeAreaView style={styles.container}>
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color={theme.colors.primary} />
          <Text style={styles.loadingText}>Loading your dashboard...</Text>
        </View>
      </SafeAreaView>
    );
  }
  
  if (error || !dashboardData) {
    return (
      <SafeAreaView style={styles.container}>
        <View style={styles.errorContainer}>
          <Ionicons name="alert-circle" size={48} color={theme.colors.error} />
          <Text style={styles.errorText}>{error || 'Unable to load data'}</Text>
        </View>
      </SafeAreaView>
    );
  }
  
  const firstName = dashboardData.user.first_name;
  const healthScore = dashboardData.health_score;
  const caloriesConsumed = dashboardData.calories.consumed;
  const caloriesGoal = dashboardData.calories.goal;
  const currentWeight = dashboardData.weight.current || 0;
  const weightHistory = dashboardData.weight.history;
  const todayWorkout = dashboardData.today_workout;
  const insight = dashboardData.insight;
  
  return (
    <SafeAreaView style={styles.container}>
      <ScrollView contentContainerStyle={styles.scrollContent} showsVerticalScrollIndicator={false}>
        
        {/* Header Section */}
        <View style={styles.header}>
          <Text style={styles.greeting}>Good Morning, <Text style={styles.nameHighlight}>{firstName}</Text></Text>
          <Text style={styles.subtitle}>Ready to crush your goals today?</Text>
        </View>

        {/* Stats Row */}
        <View style={styles.statsRow}>
          
          {/* AI Health Score Card */}
          <View style={[styles.statCard, styles.healthCard]}>
            <View style={styles.cardHeaderRow}>
               <Text style={styles.cardTitle}>AI HEALTH SCORE</Text>
               <Ionicons name="sparkles" size={14} color={theme.colors.primary} />
            </View>
            <View style={styles.healthScoreContent}>
              <View style={styles.scoreCircle}>
                <Text style={styles.scoreNumber}>{healthScore}</Text>
              </View>
              <View style={styles.scoreDetails}>
                 <Text style={styles.scoreChange}>↗ +5 pts</Text>
                 <Text style={styles.scoreSubtext}>Out of 100</Text>
              </View>
            </View>
          </View>

          {/* Calories Card */}
          <View style={[styles.statCard, styles.caloriesCard]}>
             <Text style={styles.cardTitle}>CALORIES</Text>
             <View style={styles.caloriesContent}>
               <View style={styles.fireIconContainer}>
                 <Ionicons name="flame" size={20} color="#FF6B35" />
               </View>
               <Text style={styles.calorieValue}>{caloriesConsumed.toLocaleString()}</Text>
               <Text style={styles.calorieTarget}>/ {caloriesGoal.toLocaleString()}</Text>
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
              <Text style={styles.trendTitle}>WEIGHT TREND</Text>
                <Text style={styles.trendValue}>
                  {`${currentWeight > 0 ? currentWeight.toFixed(1) : '--'} `}
                  <Text style={styles.trendUnit}>kg</Text>
                </Text>
           </View>
           
           <View style={styles.chartContainer}>
             <View style={styles.chartBars}>
                {(() => {
                  // Get last 7 days including today
                  const last7Days = [];
                  const today = new Date();
                  
                  for (let i = 6; i >= 0; i--) {
                    const date = new Date(today);
                    date.setDate(date.getDate() - i);
                    const dateStr = date.toISOString().split('T')[0];
                    
                    // Find weight entry for this date
                    const entry = weightHistory.find(w => w.recorded_date === dateStr);
                    
                    last7Days.push({
                      date: dateStr,
                      dayName: ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'][date.getDay()],
                      weight: entry ? Number(entry.weight) : null,
                      isToday: i === 0
                    });
                  }
                  
                  // Calculate max weight for scaling
                  const weights = last7Days.filter(d => d.weight !== null).map(d => d.weight!);
                  const maxWeight = weights.length > 0 ? Math.max(...weights) : 100;
                  const minWeight = weights.length > 0 ? Math.min(...weights) : 0;
                  const range = maxWeight - minWeight || 10;
                  
                  return last7Days.map((day, index) => {
                    const height = day.weight 
                      ? ((day.weight - minWeight) / range) * 70 + 30 // Scale between 30-100%
                      : 20; // Show small bar if no data
                    
                    return (
                      <View key={index} style={styles.barWrapper}>
                        <View style={styles.barContainer}>
                          <View 
                            style={[
                              styles.barFill, 
                              day.isToday && styles.barFillActive,
                              !day.weight && styles.barFillEmpty,
                              { height: `${height}%` }
                            ]}
                          />
                        </View>
                        <Text style={[styles.chartLabel, day.isToday && styles.chartLabelActive]}>
                          {day.dayName}
                        </Text>
                      </View>
                    );
                  });
                })()}
             </View>
           </View>
        </View>

        {/* AI Insight */}
        {insight && (
          <View style={styles.insightBanner}>
             <View style={styles.insightIconContainer}>
               <Ionicons name="bulb" size={20} color="#fff" />
             </View>
             <View style={styles.insightTextContent}>
               <Text style={styles.insightTitle}>AI INSIGHT</Text>
               <Text style={styles.insightBody}>{insight.text}</Text>
             </View>
          </View>
        )}

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
    marginBottom: theme.spacing.xl,
    marginTop: theme.spacing.xl,
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
    shadowColor: theme.colors.text,
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.05,
    shadowRadius: 10,
    elevation: 2,
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
