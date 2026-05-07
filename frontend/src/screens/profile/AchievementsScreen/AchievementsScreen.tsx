import { SafeAreaView } from 'react-native-safe-area-context';
import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  ActivityIndicator,
  RefreshControl,
  Share
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useNavigation, useRoute, RouteProp } from '@react-navigation/native';
import { AchievementCard } from '../../../components/cards';
import { getAchievements, Achievement, Category } from '../../../services/api/achievementService';
import { MainTabParamList } from '../../../navigation/types';

type AchievementsRouteProp = RouteProp<MainTabParamList, 'Profile'>;

export const AchievementsScreen = () => {
  const navigation = useNavigation();
  const route = useRoute<AchievementsRouteProp>();
  const [selectedCategory, setSelectedCategory] = useState<Category>('Training');
  const [achievements, setAchievements] = useState<Achievement[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const userId = (route.params as any)?.userId || 1;

  const loadAchievements = useCallback(async (showLoading = true) => {
    try {
      if (showLoading) setLoading(true);
      setError(null);
      const data = await getAchievements(userId);
      setAchievements(data);
    } catch (err) {
      setError('Failed to load achievements');
      console.error(err);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [userId]);

  useEffect(() => {
    loadAchievements();
  }, [loadAchievements]);

  const onRefresh = () => {
    setRefreshing(true);
    loadAchievements(false);
  };

  const handleShare = async () => {
    try {
      const message = `I just unlocked ${totalBadges} badges on Fitrova! 🏆\nMy top achievement: ${filteredAchievements[0]?.title || 'Staying Consistent'}\n\nJoin me on my fitness journey! #Fitrova #FitnessGoals`;
      await Share.share({
        message,
        title: 'My Fitrova Progress',
      });
    } catch (error) {
      console.error('Error sharing:', error);
    }
  };

  const filteredAchievements = achievements.filter(
    (achievement) => achievement.category === selectedCategory
  );

  const totalBadges = achievements.filter((a) => a.unlocked).length;
  const totalAchievements = achievements.length;

  const categories: Category[] = ['Training', 'Nutrition', 'Milestones'];

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView
        style={styles.scrollView}
        contentContainerStyle={styles.scrollContent}
        showsVerticalScrollIndicator={false}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor="#10B981" />
        }
      >
        {/* Header */}
        <View style={styles.header}>
          <TouchableOpacity
            style={styles.backButton}
            onPress={() => navigation.goBack()}
          >
            <Ionicons name="arrow-back" size={24} color="#1F2937" />
          </TouchableOpacity>
          <Text style={styles.headerTitle}>Achievements</Text>
          <View style={styles.placeholder} />
        </View>

        {/* Progress Card */}
        <View style={styles.progressCard}>
          <Text style={styles.progressLabel}>ELITE PERFORMER</Text>
          <Text style={styles.progressTitle}>
            You've earned <Text style={styles.progressHighlight}>{totalBadges}</Text>
            {'\n'}badges
          </Text>
          <View style={styles.progressInfo}>
            <Text style={styles.progressNext}>NEXT: MASTER TIER</Text>
            <Text style={styles.progressCount}>{totalBadges}/{totalAchievements}</Text>
          </View>
          <View style={styles.progressBarContainer}>
            <View
              style={[
                styles.progressBar,
                { width: `${(totalBadges / totalAchievements) * 100}%` },
              ]}
            />
          </View>
        </View>

        {/* Category Tabs */}
        <View style={styles.categoriesContainer}>
          {categories.map((category) => (
            <TouchableOpacity
              key={category}
              style={[
                styles.categoryTab,
                selectedCategory === category && styles.categoryTabActive,
              ]}
              onPress={() => setSelectedCategory(category)}
            >
              <Text
                style={[
                  styles.categoryText,
                  selectedCategory === category && styles.categoryTextActive,
                ]}
              >
                {category}
              </Text>
            </TouchableOpacity>
          ))}
        </View>

        {/* Category Title */}
        <Text style={styles.categoryTitle}>{selectedCategory}</Text>

        {/* Achievements Grid */}
        <View style={styles.achievementsGrid}>
          {loading ? (
            <View style={styles.centerContainer}>
              <ActivityIndicator size="large" color="#10B981" />
            </View>
          ) : error ? (
            <View style={styles.centerContainer}>
              <Text style={styles.errorText}>{error}</Text>
              <TouchableOpacity onPress={() => loadAchievements()} style={styles.retryButton}>
                <Text style={styles.retryText}>Retry</Text>
              </TouchableOpacity>
            </View>
          ) : filteredAchievements.length === 0 ? (
            <View style={styles.centerContainer}>
              <Text style={styles.noDataText}>No achievements found in this category.</Text>
            </View>
          ) : (
            filteredAchievements.map((achievement) => (
              <AchievementCard
                key={achievement.id}
                title={achievement.title}
                description={achievement.description}
                icon={achievement.unlocked ? achievement.icon : 'lock-closed'}
                color={achievement.unlocked ? achievement.color : '#E5E7EB'}
                unlocked={achievement.unlocked}
                size="small"
                style={styles.achievementCard}
              />
            ))
          )}
        </View>

        {/* Share Button */}
        <TouchableOpacity style={styles.shareButton} onPress={handleShare}>
          <Ionicons name="share-social" size={20} color="#FFFFFF" />
          <Text style={styles.shareButtonText}>Share Your Progress</Text>
        </TouchableOpacity>

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
    marginBottom: 24,
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
  placeholder: {
    width: 40,
  },
  progressCard: {
    backgroundColor: '#FFFFFF',
    borderRadius: 24,
    padding: 24,
    marginHorizontal: 20,
    marginBottom: 24,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 8,
    elevation: 2,
  },
  progressLabel: {
    fontSize: 11,
    fontWeight: '700',
    color: '#6B7280',
    letterSpacing: 1,
    marginBottom: 8,
  },
  progressTitle: {
    fontSize: 28,
    fontWeight: 'bold',
    color: '#1F2937',
    lineHeight: 36,
    marginBottom: 16,
  },
  progressHighlight: {
    color: '#10B981',
  },
  progressInfo: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  progressNext: {
    fontSize: 11,
    fontWeight: '700',
    color: '#6B7280',
    letterSpacing: 0.5,
  },
  progressCount: {
    fontSize: 14,
    fontWeight: '700',
    color: '#1F2937',
  },
  progressBarContainer: {
    height: 8,
    backgroundColor: '#E5E7EB',
    borderRadius: 4,
    overflow: 'hidden',
  },
  progressBar: {
    height: '100%',
    backgroundColor: '#10B981',
    borderRadius: 4,
  },
  categoriesContainer: {
    flexDirection: 'row',
    paddingHorizontal: 20,
    marginBottom: 24,
    gap: 12,
  },
  categoryTab: {
    paddingHorizontal: 24,
    paddingVertical: 12,
    borderRadius: 24,
    backgroundColor: '#FFFFFF',
  },
  categoryTabActive: {
    backgroundColor: '#1F2937',
  },
  categoryText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#6B7280',
  },
  categoryTextActive: {
    color: '#FFFFFF',
  },
  categoryTitle: {
    fontSize: 20,
    fontWeight: '700',
    color: '#1F2937',
    paddingHorizontal: 20,
    marginBottom: 16,
  },
  achievementsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    paddingHorizontal: 20,
    gap: 12,
    marginBottom: 24,
  },
  achievementCard: {
    width: '31%',
    aspectRatio: 1,
  },
  shareButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#1F2937',
    borderRadius: 24,
    paddingVertical: 16,
    marginHorizontal: 20,
    gap: 8,
     marginTop: 60,
  },
  shareButtonText: {
    fontSize: 16,
    fontWeight: '700',
    color: '#FFFFFF',
  },
  bottomSpacer: {
    height: 100,
  },
  centerContainer: {
    width: '100%',
    padding: 40,
    alignItems: 'center',
    justifyContent: 'center',
  },
  errorText: {
    color: '#EF4444',
    marginBottom: 12,
    fontWeight: '600',
  },
  retryButton: {
    paddingHorizontal: 20,
    paddingVertical: 10,
    backgroundColor: '#1F2937',
    borderRadius: 8,
  },
  retryText: {
    color: '#FFFFFF',
    fontWeight: '700',
  },
  noDataText: {
    color: '#6B7280',
    textAlign: 'center',
  },
});
