import { SafeAreaView } from 'react-native-safe-area-context';
import React from 'react';
import {
  View,
  Text,
  StyleSheet,
  
  ScrollView,
  TouchableOpacity} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useNavigation, useRoute, RouteProp } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { RootStackParamList, MainTabParamList } from '../../../navigation/AppNavigator';
import { StatCard, AchievementCard, ActivityCard } from '../../../components/cards';

type NavigationProp = NativeStackNavigationProp<RootStackParamList>;
type ProfileRouteProp = RouteProp<MainTabParamList, 'Profile'>;

export const ProfileScreen = () => {
  const navigation = useNavigation<NavigationProp>();
  const route = useRoute<ProfileRouteProp>();
  const userId = route.params?.userId || 1;
  const firstName = route.params?.firstName || 'User';

  const stats = [
    { label: 'WORKOUTS', value: '24' },
    { label: 'AVG\nDURATION', value: '45', unit: 'm' },
    { label: 'STREAK', value: '12', icon: 'flame' },
  ];

  const achievements = [
    { id: '1', title: '7-DAY STREAK', icon: 'trophy', color: '#D1FAE5' },
    { id: '2', title: 'PROTEIN PRO', icon: 'restaurant', color: '#FEF3C7' },
    { id: '3', title: 'IRON WILL', icon: 'barbell', color: '#1F2937' },
  ];

  const recentActivities = [
    {
      id: '1',
      title: 'Lower Body Power',
      time: 'Yesterday • 52 min',
      icon: 'fitness',
      color: '#10B981',
    },
    {
      id: '2',
      title: '5k Urban Run',
      time: '2 days ago • 24.15 min',
      icon: 'walk',
      color: '#10B981',
    },
    {
      id: '3',
      title: 'Push Day Session',
      time: '4 days ago • 65 min',
      icon: 'barbell',
      color: '#10B981',
    },
  ];

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView
        style={styles.scrollView}
        contentContainerStyle={styles.scrollContent}
        showsVerticalScrollIndicator={false}
      >
        {/* Header */}
        <View style={styles.header}>
          <TouchableOpacity style={styles.backButton}>
            <Ionicons name="arrow-back" size={24} color="#1F2937" />
          </TouchableOpacity>
          <Text style={styles.headerTitle}>Profile</Text>
          <TouchableOpacity 
            style={styles.settingsButton}
            onPress={() => navigation.navigate('Settings' as never)}
          >
            <Ionicons name="settings-outline" size={24} color="#1F2937" />
          </TouchableOpacity>
        </View>

        {/* Profile Info */}
        <View style={styles.profileSection}>
          <View style={styles.avatarContainer}>
            <View style={styles.avatar}>
              <Ionicons name="person" size={60} color="#10B981" />
            </View>
            <TouchableOpacity style={styles.editBadge}>
              <Ionicons name="pencil" size={16} color="#FFFFFF" />
            </TouchableOpacity>
          </View>
          <Text style={styles.userName}>{firstName}</Text>
          <Text style={styles.userMotto}>Striving for 1% better every day</Text>
        </View>

        {/* Stats */}
        <View style={styles.statsContainer}>
          {stats.map((stat, index) => (
            <StatCard
              key={index}
              label={stat.label}
              value={stat.value}
              unit={stat.unit}
              icon={stat.icon}
              highlighted={index === 1}
            />
          ))}
        </View>

        {/* Achievements */}
        <View style={styles.section}>
          <View style={styles.sectionHeader}>
            <Text style={styles.sectionTitle}>Achievements</Text>
            <TouchableOpacity onPress={() => navigation.navigate('Achievements')}>
              <Text style={styles.viewAllText}>VIEW ALL</Text>
            </TouchableOpacity>
          </View>
          <View style={styles.achievementsContainer}>
            {achievements.map((achievement) => (
              <AchievementCard
                key={achievement.id}
                title={achievement.title}
                icon={achievement.icon}
                color={achievement.color}
                style={styles.achievementCard}
              />
            ))}
          </View>
        </View>

        {/* Personal Records */}
        <View style={styles.recordsCard}>
          <View style={styles.recordsHeader}>
            <Text style={styles.recordsTitle}>Personal Records</Text>
            <Ionicons name="trophy" size={32} color="#374151" />
          </View>
          <View style={styles.recordsGrid}>
            <View style={styles.recordItem}>
              <Text style={styles.recordLabel}>SQUAT MAX</Text>
              <Text style={styles.recordValue}>
                100<Text style={styles.recordUnit}>kg</Text>
              </Text>
            </View>
            <View style={styles.recordItem}>
              <Text style={styles.recordLabel}>DEADLIFT MAX</Text>
              <Text style={styles.recordValue}>
                140<Text style={styles.recordUnit}>kg</Text>
              </Text>
            </View>
          </View>
        </View>

        {/* Recent Activity */}
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Recent Activity</Text>
          <View style={styles.activitiesContainer}>
            {recentActivities.map((activity) => (
              <ActivityCard
                key={activity.id}
                title={activity.title}
                subtitle={activity.time}
                icon={activity.icon}
                iconColor={activity.color}
                onPress={() => {}}
              />
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
    paddingTop: 50,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 20,
    marginBottom: 20,
  },
  backButton: {
    width: 40,
    height: 40,
    justifyContent: 'center',
  },
  headerTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: '#1F2937',
  },
  settingsButton: {
    width: 40,
    height: 40,
    justifyContent: 'center',
    alignItems: 'flex-end',
  },
  profileSection: {
    alignItems: 'center',
    marginBottom: 24,
    paddingHorizontal: 20,
  },
  avatarContainer: {
    position: 'relative',
    marginBottom: 16,
  },
  avatar: {
    width: 120,
    height: 120,
    borderRadius: 60,
    backgroundColor: '#D1FAE5',
    justifyContent: 'center',
    alignItems: 'center',
    borderWidth: 4,
    borderColor: '#10B981',
  },
  editBadge: {
    position: 'absolute',
    bottom: 0,
    right: 0,
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: '#10B981',
    justifyContent: 'center',
    alignItems: 'center',
    borderWidth: 3,
    borderColor: '#F9FAFB',
  },
  userName: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#1F2937',
    marginBottom: 4,
  },
  userMotto: {
    fontSize: 14,
    color: '#6B7280',
  },
  statsContainer: {
    flexDirection: 'row',
    paddingHorizontal: 20,
    marginBottom: 32,
    gap: 12,
  },
  section: {
    paddingHorizontal: 20,
    marginBottom: 24,
  },
  sectionHeader: {
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
  viewAllText: {
    fontSize: 12,
    fontWeight: '700',
    color: '#10B981',
    letterSpacing: 0.5,
  },
  achievementsContainer: {
    flexDirection: 'row',
    gap: 12,
  },
  achievementCard: {
    flex: 1,
  },
  recordsCard: {
    backgroundColor: '#1F2937',
    borderRadius: 24,
    padding: 24,
    marginHorizontal: 20,
    marginBottom: 24,
  },
  recordsHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 20,
  },
  recordsTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: '#FFFFFF',
  },
  recordsGrid: {
    flexDirection: 'row',
    gap: 16,
  },
  recordItem: {
    flex: 1,
    backgroundColor: '#374151',
    borderRadius: 16,
    padding: 16,
  },
  recordLabel: {
    fontSize: 10,
    fontWeight: '700',
    color: '#10B981',
    letterSpacing: 0.5,
    marginBottom: 8,
  },
  recordValue: {
    fontSize: 32,
    fontWeight: 'bold',
    color: '#FFFFFF',
  },
  recordUnit: {
    fontSize: 16,
    fontWeight: '600',
    color: '#9CA3AF',
  },
  activitiesContainer: {
    gap: 12,
  },
  bottomSpacer: {
    height: 100,
  },
});
