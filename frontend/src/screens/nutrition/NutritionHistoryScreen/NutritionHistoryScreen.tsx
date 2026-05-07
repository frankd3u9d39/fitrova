import React, { useState, useEffect } from 'react';
import {
  StyleSheet,
  Text,
  View,
  ScrollView,
  TouchableOpacity,
  TextInput,
  ActivityIndicator,
  Dimensions,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { useNavigation, useRoute, RouteProp } from '@react-navigation/native';
import { theme } from '../../../theme';
import { nutritionService, Meal } from '../../../services/api/nutritionService';
import { RootStackParamList } from '../../../navigation/types';

const { width } = Dimensions.get('window');

type HistoryRouteProp = RouteProp<RootStackParamList, 'NutritionHistory'>;

export const NutritionHistoryScreen = () => {
  const navigation = useNavigation<any>();
  const route = useRoute<HistoryRouteProp>();
  const userId = route.params?.userId || 1;

  const [loading, setLoading] = useState(true);
  const [meals, setMeals] = useState<Meal[]>([]);
  const [searchQuery, setSearchQuery] = useState('');
  const [filteredMeals, setFilteredMeals] = useState<Meal[]>([]);

  useEffect(() => {
    fetchHistory();
  }, []);

  useEffect(() => {
    if (searchQuery.trim() === '') {
      setFilteredMeals(meals);
    } else {
      const filtered = meals.filter(meal => 
        meal.meal_name.toLowerCase().includes(searchQuery.toLowerCase()) ||
        meal.meal_type.toLowerCase().includes(searchQuery.toLowerCase())
      );
      setFilteredMeals(filtered);
    }
  }, [searchQuery, meals]);

  const fetchHistory = async () => {
    try {
      setLoading(true);
      const data = await nutritionService.getNutritionData(userId);
      setMeals(data.meals);
      setFilteredMeals(data.meals);
    } catch (error) {
      console.error('History fetch error:', error);
    } finally {
      setLoading(false);
    }
  };

  const getMealIcon = (type: string) => {
    switch (type.toLowerCase()) {
      case 'breakfast': return 'sunny';
      case 'lunch': return 'partly-sunny';
      case 'dinner': return 'moon';
      case 'snack': return 'nutrition';
      default: return 'restaurant';
    }
  };

  const getMealColor = (type: string) => {
    switch (type.toLowerCase()) {
      case 'breakfast': return '#F59E0B';
      case 'lunch': return '#10B981';
      case 'dinner': return '#6366F1';
      case 'snack': return '#EC4899';
      default: return '#9CA3AF';
    }
  };

  if (loading) {
    return (
      <SafeAreaView style={styles.loadingContainer}>
        <ActivityIndicator size="large" color="#10B981" />
        <Text style={styles.loadingText}>Loading history...</Text>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.container}>
      {/* Header */}
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
          <Ionicons name="chevron-back" size={28} color="#1F2937" />
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Nutrition History</Text>
        <View style={{ width: 40 }} />
      </View>

      {/* Search */}
      <View style={styles.searchContainer}>
        <Ionicons name="search" size={20} color="#9CA3AF" />
        <TextInput
          style={styles.searchInput}
          placeholder="Search by meal name or type..."
          value={searchQuery}
          onChangeText={setSearchQuery}
          placeholderTextColor="#9CA3AF"
        />
        {searchQuery.length > 0 && (
          <TouchableOpacity onPress={() => setSearchQuery('')}>
            <Ionicons name="close-circle" size={20} color="#9CA3AF" />
          </TouchableOpacity>
        )}
      </View>

      <ScrollView 
        style={styles.scrollView}
        contentContainerStyle={styles.scrollContent}
        showsVerticalScrollIndicator={false}
      >
        {filteredMeals.length === 0 ? (
          <View style={styles.emptyState}>
            <Ionicons name="search-outline" size={64} color="#E5E7EB" />
            <Text style={styles.emptyText}>No matching meals found</Text>
          </View>
        ) : (
          filteredMeals.map((meal) => (
            <View key={meal.id} style={styles.mealCard}>
              <View style={styles.mealHeader}>
                <View style={styles.mealHeaderLeft}>
                  <View style={[styles.mealIconContainer, { backgroundColor: getMealColor(meal.meal_type) }]}>
                    <Ionicons name={getMealIcon(meal.meal_type) as any} size={20} color="#fff" />
                  </View>
                  <View style={{ flex: 1 }}>
                    <Text style={styles.mealName} numberOfLines={1}>{meal.meal_name}</Text>
                    <Text style={styles.mealTime}>{meal.meal_time}</Text>
                  </View>
                </View>
                <View style={styles.mealCaloriesContainer}>
                  <Text style={styles.mealCalories}>{meal.calories}</Text>
                  <Text style={styles.mealCaloriesUnit}>kcal</Text>
                </View>
              </View>

              <View style={styles.macroSummary}>
                <View style={styles.macroPill}>
                  <Text style={[styles.macroPillLabel, { color: '#3B82F6' }]}>P</Text>
                  <Text style={styles.macroPillValue}>{meal.protein || 0}g</Text>
                </View>
                <View style={styles.macroPill}>
                  <Text style={[styles.macroPillLabel, { color: '#F59E0B' }]}>C</Text>
                  <Text style={styles.macroPillValue}>{meal.carbs || 0}g</Text>
                </View>
                <View style={styles.macroPill}>
                  <Text style={[styles.macroPillLabel, { color: '#EF4444' }]}>F</Text>
                  <Text style={styles.macroPillValue}>{meal.fats || 0}g</Text>
                </View>
              </View>
            </View>
          ))
        )}
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
    backgroundColor: '#fff',
  },
  loadingText: {
    marginTop: 10,
    color: '#9CA3AF',
    fontSize: 16,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 20,
    paddingVertical: 16,
    backgroundColor: '#fff',
    borderBottomWidth: 1,
    borderBottomColor: '#F3F4F6',
  },
  backButton: {
    width: 40,
    height: 40,
    borderRadius: 20,
    justifyContent: 'center',
    alignItems: 'center',
  },
  headerTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: '#1F2937',
  },
  searchContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#FFFFFF',
    margin: 20,
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderRadius: 16,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 10,
    elevation: 2,
    borderWidth: 1,
    borderColor: '#F3F4F6',
  },
  searchInput: {
    flex: 1,
    marginLeft: 12,
    fontSize: 16,
    color: '#1F2937',
  },
  scrollView: {
    flex: 1,
  },
  scrollContent: {
    paddingHorizontal: 20,
    paddingBottom: 40,
  },
  mealCard: {
    backgroundColor: '#FFFFFF',
    borderRadius: 24,
    padding: 20,
    marginBottom: 16,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.03,
    shadowRadius: 12,
    elevation: 2,
    borderWidth: 1,
    borderColor: '#F3F4F6',
  },
  mealHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 16,
  },
  mealHeaderLeft: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
    gap: 12,
  },
  mealIconContainer: {
    width: 44,
    height: 44,
    borderRadius: 14,
    justifyContent: 'center',
    alignItems: 'center',
  },
  mealName: {
    fontSize: 17,
    fontWeight: '800',
    color: '#111827',
  },
  mealTime: {
    fontSize: 13,
    color: '#9CA3AF',
    fontWeight: '600',
  },
  mealCaloriesContainer: {
    alignItems: 'flex-end',
  },
  mealCalories: {
    fontSize: 22,
    fontWeight: '900',
    color: '#111827',
  },
  mealCaloriesUnit: {
    fontSize: 11,
    fontWeight: '700',
    color: '#9CA3AF',
    textTransform: 'uppercase',
  },
  macroSummary: {
    flexDirection: 'row',
    gap: 8,
  },
  macroPill: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#F9FAFB',
    paddingVertical: 6,
    borderRadius: 10,
    gap: 4,
  },
  macroPillLabel: {
    fontSize: 12,
    fontWeight: '900',
  },
  macroPillValue: {
    fontSize: 12,
    fontWeight: '700',
    color: '#4B5563',
  },
  emptyState: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 80,
  },
  emptyText: {
    marginTop: 16,
    fontSize: 16,
    color: '#9CA3AF',
    fontWeight: '600',
  },
});
