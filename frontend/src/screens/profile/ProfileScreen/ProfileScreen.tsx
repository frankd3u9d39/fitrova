import { SafeAreaView } from 'react-native-safe-area-context';
import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  ActivityIndicator,
  Image,
  RefreshControl
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useNavigation, useRoute, RouteProp, useFocusEffect } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { RootStackParamList, MainTabParamList } from '../../../navigation/types';
import { StatCard, AchievementCard, ActivityCard } from '../../../components/cards';
import { getProfileStats, ProfileStats } from '../../../services/api/profileService';
import { getAchievements, Achievement } from '../../../services/api/achievementService';

type NavigationProp = NativeStackNavigationProp<RootStackParamList>;
type ProfileRouteProp = RouteProp<MainTabParamList, 'Profile'>;

export const ProfileScreen = () => {
  const navigation = useNavigation<NavigationProp>();
  const route = useRoute<ProfileRouteProp>();
  const userId = route.params?.userId || 1;
  
  const [profileData, setProfileData] = useState<ProfileStats | null>(null);
  const [achievements, setAchievements] = useState<Achievement[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
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

  const onRefresh = async () => {
    setRefreshing(true);
    await loadProfileData(true);
    setRefreshing(false);
  };

  const getPlanLabel = (tier: string) => {
    switch (tier?.toLowerCase()) {
      case 'advanced_premium':
        return 'Advanced Premium';
      case 'premium':
        return 'Premium AI';
      default:
        return 'Free Plan';
    }
  };

  const getPlanStyle = (tier: string) => {
    switch (tier?.toLowerCase()) {
      case 'advanced_premium':
        return {
          backgroundColor: '#ECFDF5',
          color: '#10B981',
          borderColor: '#A7F3D0'
        };
      case 'premium':
        return {
          backgroundColor: '#EFF6FF',
          color: '#3B82F6',
          borderColor: '#BFDBFE'
        };
      default:
        return {
          backgroundColor: '#F3F4F6',
          color: '#6B7280',
          borderColor: '#E5E7EB'
        };
    }
  };

  useFocusEffect(
    React.useCallback(() => {
      loadProfileData(!!profileData);
    }, [userId])
  );

  const loadProfileData = async (silent = false) => {
    try {
      if (!silent) setLoading(true);
      const [stats, achievementsData] = await Promise.all([
        getProfileStats(userId),
        getAchievements(userId)
      ]);
      setProfileData(stats);
      setAchievements(achievementsData.filter(a => a.unlocked).slice(0, 3));
      setError(null);
    } catch (err) {
      console.error('Profile data error:', err);
      setError('Unable to load profile data');
    } finally {
      if (!silent) setLoading(false);
    }
  };

  if (loading) {
    return (
      <SafeAreaView style={[styles.container, { backgroundColor: colors.background }]}>
        <View style={[styles.loadingContainer, { backgroundColor: colors.background }]}>
          <ActivityIndicator size="large" color="#10B981" />
          <Text style={[styles.loadingText, { color: colors.textSecondary }]}>Loading profile...</Text>
        </View>
      </SafeAreaView>
    );
  }

  if (error || !profileData) {
    return (
      <SafeAreaView style={[styles.container, { backgroundColor: colors.background }]}>
        <View style={[styles.errorContainer, { backgroundColor: colors.background }]}>
          <Ionicons name="alert-circle" size={48} color="#EF4444" />
          <Text style={[styles.errorText, { color: colors.text }]}>{error || 'Unable to load profile'}</Text>
          <TouchableOpacity style={styles.retryButton} onPress={() => loadProfileData()}>
            <Text style={styles.retryButtonText}>Retry</Text>
          </TouchableOpacity>
        </View>
      </SafeAreaView>
    );
  }

  const stats = [
    { label: 'WORKOUTS', value: profileData.stats.total_workouts.toString() },
    { label: 'AVG\nDURATION', value: profileData.stats.avg_duration.toString(), unit: 'm' },
    { label: 'STREAK', value: profileData.stats.streak.toString(), icon: 'flame' },
  ];

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: colors.background }]}>
      <ScrollView
        style={[styles.scrollView, { backgroundColor: colors.background }]}
        contentContainerStyle={styles.scrollContent}
        showsVerticalScrollIndicator={false}
        refreshControl={
          <RefreshControl
            refreshing={refreshing}
            onRefresh={onRefresh}
            colors={['#10B981']}
            tintColor="#10B981"
          />
        }
      >
        {/* Header */}
        <View style={styles.header}>
          <TouchableOpacity style={styles.backButton}>
            <Ionicons name="arrow-back" size={24} color={colors.text} />
          </TouchableOpacity>
          <Text style={[styles.headerTitle, { color: colors.text }]}>Profile</Text>
          <TouchableOpacity 
            style={styles.settingsButton}
            onPress={() => navigation.navigate('Settings', { userId })}
          >
            <Ionicons name="settings-outline" size={24} color={colors.text} />
          </TouchableOpacity>
        </View>

        {/* Profile Info */}
        <View style={styles.profileSection}>
          <View style={styles.avatarContainer}>
            <View style={[styles.avatar, darkTheme && { backgroundColor: '#1E293B', borderColor: '#10B981' }]}>
              {profileData.user.profile_picture ? (
                <Image
                  source={{ uri: profileData.user.profile_picture }}
                  style={styles.avatarImage}
                />
              ) : (
                <Text style={styles.avatarText}>
                  {((profileData.user.first_name?.[0] || '') + (profileData.user.last_name?.[0] || '')).toUpperCase() || 'U'}
                </Text>
              )}
            </View>
            <TouchableOpacity 
              style={[styles.editBadge, darkTheme && { borderColor: '#0F172A' }]}
              onPress={() => navigation.navigate('EditProfile', { userId })}
            >
              <Ionicons name="pencil" size={16} color="#FFFFFF" />
            </TouchableOpacity>
          </View>
          <Text style={[styles.userName, { color: colors.text }]}>{profileData.user.first_name} {profileData.user.last_name}</Text>
          
          <View style={[
            styles.planBadge, 
            { 
              backgroundColor: getPlanStyle(profileData.user.subscription_tier).backgroundColor,
              borderColor: getPlanStyle(profileData.user.subscription_tier).borderColor 
            }
          ]}>
            <Ionicons 
              name={profileData.user.subscription_tier?.toLowerCase().includes('premium') ? 'star' : 'star-outline'} 
              size={12} 
              color={getPlanStyle(profileData.user.subscription_tier).color} 
              style={{ marginRight: 4 }}
            />
            <Text style={[
              styles.planText, 
              { color: getPlanStyle(profileData.user.subscription_tier).color }
            ]}>
              {getPlanLabel(profileData.user.subscription_tier)}
            </Text>
          </View>

          <Text style={[styles.userMotto, { color: colors.textSecondary }]}>{profileData.user.motto}</Text>
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
              dark={darkTheme}
            />
          ))}
        </View>

        {/* Achievements */}
        <View style={styles.section}>
          <View style={styles.sectionHeader}>
            <Text style={[styles.sectionTitle, { color: colors.text }]}>Achievements</Text>
            <TouchableOpacity onPress={() => navigation.navigate('Achievements')}>
              <Text style={styles.viewAllText}>VIEW ALL</Text>
            </TouchableOpacity>
          </View>
          <View style={styles.achievementsContainer}>
            {achievements.length > 0 ? (
              achievements.map((achievement) => (
                <AchievementCard
                  key={achievement.id}
                  title={achievement.title}
                  icon={achievement.icon}
                  color={achievement.color}
                  style={styles.achievementCard}
                  dark={darkTheme}
                />
              ))
            ) : (
              <Text style={[styles.emptyText, { color: colors.textSecondary }]}>Complete workouts to unlock achievements!</Text>
            )}
          </View>
        </View>

        {/* Personal Records */}
        {profileData.personal_records.length > 0 && (
          <View style={[styles.recordsCard, darkTheme && { backgroundColor: '#1E293B' }]}>
            <View style={styles.recordsHeader}>
              <Text style={[styles.recordsTitle, darkTheme && { color: '#F8FAFC' }]}>Personal Records</Text>
              <Ionicons name="trophy" size={32} color={darkTheme ? '#10B981' : '#374151'} />
            </View>
            <View style={styles.recordsGrid}>
              {profileData.personal_records.map((record, index) => (
                <View key={index} style={[styles.recordItem, darkTheme && { backgroundColor: '#334155' }]}>
                  <Text style={[styles.recordLabel, darkTheme && { color: '#34D399' }]}>{record.exercise_name.toUpperCase()}</Text>
                  <Text style={[styles.recordValue, darkTheme && { color: '#F8FAFC' }]}>
                    {record.max_weight}<Text style={[styles.recordUnit, darkTheme && { color: '#94A3B8' }]}>kg</Text>
                  </Text>
                </View>
              ))}
            </View>
          </View>
        )}

        {/* Recent Activity */}
        <View style={styles.section}>
          <Text style={[styles.sectionTitle, { color: colors.text }]}>Recent Activity</Text>
          <View style={styles.activitiesContainer}>
            {profileData.recent_activities.length > 0 ? (
              profileData.recent_activities.map((activity, index) => (
                <ActivityCard
                  key={index}
                  title={activity.title}
                  subtitle={activity.time}
                  icon={activity.icon}
                  iconColor={activity.color}
                  onPress={() => {}}
                  dark={darkTheme}
                />
              ))
            ) : (
              <Text style={[styles.emptyText, { color: colors.textSecondary }]}>No recent activities yet. Start your first workout!</Text>
            )}
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
  emptyText: {
    fontSize: 14,
    color: '#9CA3AF',
    textAlign: 'center',
    paddingVertical: 20,
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
    overflow: 'hidden',
  },
  avatarText: {
    fontSize: 36,
    fontWeight: '800',
    color: '#10B981',
    letterSpacing: 0.5,
  },
  avatarImage: {
    width: '100%',
    height: '100%',
    borderRadius: 60,
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
  planBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 10,
    paddingVertical: 3,
    borderRadius: 8,
    borderWidth: 1,
    marginTop: 4,
    marginBottom: 8,
  },
  planText: {
    fontSize: 10,
    fontWeight: '700',
    textTransform: 'uppercase',
    letterSpacing: 0.5,
  },
});
