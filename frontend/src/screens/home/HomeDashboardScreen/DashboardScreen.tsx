import React from 'react';
import { View, Text, StyleSheet, SafeAreaView, ScrollView, ImageBackground } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { theme } from '../../../theme';
import { useRoute, RouteProp } from '@react-navigation/native';
import { MainTabParamList } from '../../../navigation/AppNavigator';

type DashboardRouteProp = RouteProp<MainTabParamList, 'Home'>;

export const DashboardScreen = () => {
  const route = useRoute<DashboardRouteProp>();
  const firstName = route.params?.firstName || 'User';
  
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
                <Text style={styles.scoreNumber}>82</Text>
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
               <Text style={styles.calorieValue}>1,240</Text>
               <Text style={styles.calorieTarget}>/ 1,800</Text>
             </View>
          </View>

        </View>

        {/* Today's Workout Hero */}
        <View style={styles.workoutCard}>
          <View style={styles.workoutCardOverlay}>
             <Text style={styles.workoutSubtitle}>TODAY'S WORKOUT</Text>
             <Text style={styles.workoutTitle}>Lower Body{'\n'}Power</Text>
             <View style={styles.durationBadge}>
               <Ionicons name="time-outline" size={12} color="#fff" />
               <Text style={styles.durationText}> 45 min</Text>
             </View>
          </View>
        </View>

        {/* Weight Trend */}
        <View style={styles.trendSection}>
           <View style={styles.trendHeader}>
              <Text style={styles.trendTitle}>WEIGHT TREND</Text>
              <Text style={styles.trendValue}>72.4 <Text style={styles.trendUnit}>kg</Text></Text>
           </View>
           
           <View style={styles.chartContainer}>
             {/* Mocking a bar chart */}
             <View style={styles.chartBars}>
                <View style={[styles.barContainer, { height: '50%' }]}><View style={styles.barFill}/></View>
                <View style={[styles.barContainer, { height: '55%' }]}><View style={styles.barFill}/></View>
                <View style={[styles.barContainer, { height: '48%' }]}><View style={styles.barFill}/></View>
                <View style={[styles.barContainer, { height: '45%' }]}><View style={styles.barFill}/></View>
                <View style={[styles.barContainer, { height: '40%' }]}><View style={styles.barFill}/></View>
                <View style={[styles.barContainer, { height: '35%' }]}><View style={styles.barFill}/></View>
                <View style={[styles.barContainer, { height: '32%' }]}><View style={[styles.barFill, styles.barFillActive]}/></View>
             </View>
             <View style={styles.chartLabels}>
                <Text style={styles.chartLabel}>MON</Text>
                <Text style={styles.chartLabel}>TUE</Text>
                <Text style={styles.chartLabel}>WED</Text>
                <Text style={styles.chartLabel}>THU</Text>
                <Text style={styles.chartLabel}>FRI</Text>
                <Text style={styles.chartLabel}>SAT</Text>
                <Text style={[styles.chartLabel, styles.chartLabelActive]}>SUN</Text>
             </View>
           </View>
        </View>

        {/* AI Insight */}
        <View style={styles.insightBanner}>
           <View style={styles.insightIconContainer}>
             <Ionicons name="bulb" size={20} color="#fff" />
           </View>
           <View style={styles.insightTextContent}>
             <Text style={styles.insightTitle}>AI INSIGHT</Text>
             <Text style={styles.insightBody}>You're <Text style={styles.insightHighlight}>15% more consistent</Text> this week. Keep it up!</Text>
           </View>
        </View>

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
    height: 140,
    justifyContent: 'space-between',
  },
  chartBars: {
    flex: 1,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-end',
    marginBottom: theme.spacing.sm,
  },
  barContainer: {
    width: 24,
    justifyContent: 'flex-end',
  },
  barFill: {
    width: '100%',
    backgroundColor: theme.colors.border,
    borderRadius: 4,
    height: '100%',
  },
  barFillActive: {
    backgroundColor: theme.colors.primary,
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
