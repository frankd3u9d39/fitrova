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
  Modal,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { useNavigation, useRoute, RouteProp, useFocusEffect } from '@react-navigation/native';
import AsyncStorage from '@react-native-async-storage/async-storage';
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
  const [selectedMeal, setSelectedMeal] = useState<Meal | null>(null);
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
      const data = await nutritionService.getNutritionData(userId, true);
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
      <SafeAreaView style={[styles.loadingContainer, { backgroundColor: colors.background }]}>
        <ActivityIndicator size="large" color="#10B981" />
        <Text style={[styles.loadingText, { color: colors.textSecondary }]}>Loading history...</Text>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: colors.background }]}>
      {/* Header */}
      <View style={[styles.header, { backgroundColor: colors.cardBg, borderBottomColor: colors.border }]}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
          <Ionicons name="chevron-back" size={28} color={colors.text} />
        </TouchableOpacity>
        <Text style={[styles.headerTitle, { color: colors.text }]}>Nutrition History</Text>
        <View style={{ width: 40 }} />
      </View>

      {/* Search */}
      <View style={[styles.searchContainer, { backgroundColor: colors.cardBg, borderColor: colors.border }]}>
        <Ionicons name="search" size={20} color="#9CA3AF" />
        <TextInput
          style={[styles.searchInput, { color: colors.text }]}
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
        style={[styles.scrollView, { backgroundColor: colors.background }]}
        contentContainerStyle={styles.scrollContent}
        showsVerticalScrollIndicator={false}
      >
        {filteredMeals.length === 0 ? (
          <View style={styles.emptyState}>
            <Ionicons name="search-outline" size={64} color={darkTheme ? '#334155' : '#E5E7EB'} />
            <Text style={[styles.emptyText, { color: colors.textSecondary }]}>No matching meals found</Text>
          </View>
        ) : (
          filteredMeals.map((meal) => (
            <TouchableOpacity 
              key={meal.id} 
              style={[styles.mealCard, { backgroundColor: colors.cardBg, borderColor: colors.border }]}
              onPress={() => setSelectedMeal(meal)}
              activeOpacity={0.7}
            >
              <View style={styles.mealHeader}>
                <View style={styles.mealHeaderLeft}>
                  <View style={[styles.mealIconContainer, { backgroundColor: getMealColor(meal.meal_type) }]}>
                    <Ionicons name={getMealIcon(meal.meal_type) as any} size={20} color="#fff" />
                  </View>
                  <View style={{ flex: 1 }}>
                    <Text style={[styles.mealName, { color: colors.text }]} numberOfLines={1}>{meal.meal_name}</Text>
                    <Text style={styles.mealTime}>{meal.meal_time}</Text>
                  </View>
                </View>
                <View style={styles.mealCaloriesContainer}>
                  <Text style={[styles.mealCalories, { color: colors.text }]}>{meal.calories}</Text>
                  <Text style={styles.mealCaloriesUnit}>kcal</Text>
                </View>
              </View>

              <View style={styles.macroSummary}>
                <View style={[styles.macroPill, { backgroundColor: darkTheme ? '#334155' : '#F9FAFB' }]}>
                  <Text style={[styles.macroPillLabel, { color: '#3B82F6' }]}>P</Text>
                  <Text style={[styles.macroPillValue, { color: colors.textSecondary }]}>{meal.protein || 0}g</Text>
                </View>
                <View style={[styles.macroPill, { backgroundColor: darkTheme ? '#334155' : '#F9FAFB' }]}>
                  <Text style={[styles.macroPillLabel, { color: '#F59E0B' }]}>C</Text>
                  <Text style={[styles.macroPillValue, { color: colors.textSecondary }]}>{meal.carbs || 0}g</Text>
                </View>
                <View style={[styles.macroPill, { backgroundColor: darkTheme ? '#334155' : '#F9FAFB' }]}>
                  <Text style={[styles.macroPillLabel, { color: '#EF4444' }]}>F</Text>
                  <Text style={[styles.macroPillValue, { color: colors.textSecondary }]}>{meal.fats || 0}g</Text>
                </View>
              </View>
            </TouchableOpacity>
          ))
        )}
      </ScrollView>

      {/* Meal Detail Modal */}
      <Modal
        visible={selectedMeal !== null}
        transparent={true}
        animationType="fade"
        onRequestClose={() => setSelectedMeal(null)}
      >
        <View style={styles.modalOverlay}>
          <View style={[styles.modalContent, { backgroundColor: colors.cardBg }]}>
            {selectedMeal && (
              <>
                {/* Modal Header */}
                <View style={styles.modalHeader}>
                  <View style={[styles.modalIconContainer, { backgroundColor: getMealColor(selectedMeal.meal_type) }]}>
                    <Ionicons name={getMealIcon(selectedMeal.meal_type) as any} size={24} color="#fff" />
                  </View>
                  <Text style={styles.modalSubtitle}>{selectedMeal.meal_type.toUpperCase()}</Text>
                  <TouchableOpacity onPress={() => setSelectedMeal(null)} style={[styles.closeButton, { backgroundColor: darkTheme ? '#334155' : '#F3F4F6' }]}>
                    <Ionicons name="close" size={24} color={colors.textSecondary} />
                  </TouchableOpacity>
                </View>

                {/* Scrollable Modal Body */}
                <ScrollView 
                  style={styles.modalScrollBody}
                  showsVerticalScrollIndicator={false}
                  contentContainerStyle={{ paddingBottom: 16 }}
                >
                  <View style={styles.modalBody}>
                    <Text style={[styles.modalMealName, { color: colors.text }]}>{selectedMeal.meal_name}</Text>
                    <Text style={[styles.modalMealTime, { color: colors.textSecondary }]}>Logged at {selectedMeal.meal_time}</Text>

                    <View style={[styles.caloriesBadge, darkTheme && { backgroundColor: '#064E3B', borderColor: '#065F46' }]}>
                      <Text style={[styles.caloriesValue, darkTheme && { color: '#34D399' }]}>{selectedMeal.calories}</Text>
                      <Text style={[styles.caloriesLabel, darkTheme && { color: '#A7F3D0' }]}>Total Calories (kcal)</Text>
                    </View>

                    {/* Meal Items Section */}
                    {selectedMeal.items && selectedMeal.items.length > 0 && (
                      <View style={[styles.itemsSection, { backgroundColor: darkTheme ? '#0F172A' : '#F9FAFB', borderColor: colors.border }]}>
                        <Text style={[styles.sectionTitle, { color: colors.text }]}>Meal Items</Text>
                        {selectedMeal.items.map((item, idx) => (
                          <View key={idx} style={[styles.itemRow, { borderBottomColor: colors.border }]}>
                            <View style={styles.itemRowLeft}>
                              <Ionicons name="ellipse" size={6} color="#10B981" style={styles.bulletIcon} />
                              <View style={{ flex: 1, paddingLeft: 4 }}>
                                <Text style={[styles.itemName, { color: colors.text }]}>{item.name}</Text>
                                {item.amount && <Text style={[styles.itemAmount, { color: colors.textSecondary }]}>{item.amount}</Text>}
                              </View>
                            </View>
                            <Text style={[styles.itemCalories, { color: colors.textSecondary }]}>{item.calories} kcal</Text>
                          </View>
                        ))}
                      </View>
                    )}

                    {/* Macros Breakdown Section */}
                    <Text style={[styles.sectionTitle, { color: colors.text }]}>Macronutrients Breakdown</Text>

                    {/* Protein */}
                    <View style={styles.macroRow}>
                      <View style={styles.macroInfo}>
                        <Text style={[styles.macroLabel, { color: '#3B82F6' }]}>Protein</Text>
                        <Text style={[styles.macroValue, { color: colors.textSecondary }]}>{selectedMeal.protein || 0}g</Text>
                      </View>
                      <View style={[styles.progressBarBg, { backgroundColor: darkTheme ? '#334155' : '#E5E7EB' }]}>
                        <View style={[styles.progressBarFill, { width: `${Math.min(100, Math.round((selectedMeal.protein || 0) * 1.5))}%`, backgroundColor: '#3B82F6' }]} />
                      </View>
                    </View>

                    {/* Carbs */}
                    <View style={styles.macroRow}>
                      <View style={styles.macroInfo}>
                        <Text style={[styles.macroLabel, { color: '#F59E0B' }]}>Carbohydrates</Text>
                        <Text style={[styles.macroValue, { color: colors.textSecondary }]}>{selectedMeal.carbs || 0}g</Text>
                      </View>
                      <View style={[styles.progressBarBg, { backgroundColor: darkTheme ? '#334155' : '#E5E7EB' }]}>
                        <View style={[styles.progressBarFill, { width: `${Math.min(100, Math.round((selectedMeal.carbs || 0) * 1.2))}%`, backgroundColor: '#F59E0B' }]} />
                      </View>
                    </View>

                    {/* Fats */}
                    <View style={styles.macroRow}>
                      <View style={styles.macroInfo}>
                        <Text style={[styles.macroLabel, { color: '#EF4444' }]}>Fats</Text>
                        <Text style={[styles.macroValue, { color: colors.textSecondary }]}>{selectedMeal.fats || 0}g</Text>
                      </View>
                      <View style={[styles.progressBarBg, { backgroundColor: darkTheme ? '#334155' : '#E5E7EB' }]}>
                        <View style={[styles.progressBarFill, { width: `${Math.min(100, Math.round((selectedMeal.fats || 0) * 2.5))}%`, backgroundColor: '#EF4444' }]} />
                      </View>
                    </View>
                  </View>
                </ScrollView>

                {/* Primary Action Button */}
                <TouchableOpacity onPress={() => setSelectedMeal(null)} style={styles.modalActionButton}>
                  <Text style={styles.modalActionButtonText}>Back to History</Text>
                </TouchableOpacity>
              </>
            )}
          </View>
        </View>
      </Modal>
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
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(15, 23, 42, 0.4)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 24,
  },
  modalContent: {
    backgroundColor: '#FFFFFF',
    borderRadius: 32,
    width: '100%',
    maxHeight: '90%',
    padding: 24,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.1,
    shadowRadius: 20,
    elevation: 8,
  },
  modalHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 20,
  },
  modalIconContainer: {
    width: 48,
    height: 48,
    borderRadius: 16,
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 12,
  },
  modalSubtitle: {
    fontSize: 12,
    fontWeight: '800',
    color: '#9CA3AF',
    letterSpacing: 1.5,
    flex: 1,
  },
  closeButton: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: '#F3F4F6',
    justifyContent: 'center',
    alignItems: 'center',
  },
  modalBody: {
    marginBottom: 24,
  },
  modalMealName: {
    fontSize: 22,
    fontWeight: '900',
    color: '#111827',
    marginBottom: 4,
  },
  modalMealTime: {
    fontSize: 14,
    color: '#9CA3AF',
    fontWeight: '600',
    marginBottom: 20,
  },
  caloriesBadge: {
    backgroundColor: '#ECFDF5',
    borderRadius: 20,
    paddingVertical: 16,
    paddingHorizontal: 20,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: '#A7F3D0',
    marginBottom: 24,
  },
  caloriesValue: {
    fontSize: 32,
    fontWeight: '900',
    color: '#10B981',
  },
  caloriesLabel: {
    fontSize: 12,
    fontWeight: '700',
    color: '#047857',
    marginTop: 4,
    textTransform: 'uppercase',
  },
  sectionTitle: {
    fontSize: 15,
    fontWeight: '800',
    color: '#111827',
    marginBottom: 16,
    letterSpacing: 0.3,
  },
  macroRow: {
    marginBottom: 16,
  },
  macroInfo: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 6,
  },
  macroLabel: {
    fontSize: 14,
    fontWeight: '800',
  },
  macroValue: {
    fontSize: 14,
    fontWeight: '700',
    color: '#4B5563',
  },
  progressBarBg: {
    height: 8,
    width: '100%',
    backgroundColor: '#E5E7EB',
    borderRadius: 4,
    overflow: 'hidden',
  },
  progressBarFill: {
    height: '100%',
    borderRadius: 4,
  },
  modalActionButton: {
    backgroundColor: '#10B981',
    borderRadius: 16,
    paddingVertical: 14,
    alignItems: 'center',
    justifyContent: 'center',
  },
  modalActionButtonText: {
    fontSize: 16,
    fontWeight: '800',
    color: '#FFFFFF',
  },
  modalScrollBody: {
    flexGrow: 0,
    marginBottom: 16,
  },
  itemsSection: {
    marginBottom: 24,
    backgroundColor: '#F9FAFB',
    borderRadius: 20,
    padding: 16,
    borderWidth: 1,
    borderColor: '#F3F4F6',
  },
  itemRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 10,
    borderBottomWidth: 1,
    borderBottomColor: '#F3F4F6',
  },
  itemRowLeft: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
  },
  bulletIcon: {
    marginRight: 6,
  },
  itemName: {
    fontSize: 14,
    fontWeight: '700',
    color: '#1F2937',
  },
  itemAmount: {
    fontSize: 12,
    fontWeight: '500',
    color: '#9CA3AF',
    marginTop: 2,
  },
  itemCalories: {
    fontSize: 14,
    fontWeight: '700',
    color: '#4B5563',
  },
});
