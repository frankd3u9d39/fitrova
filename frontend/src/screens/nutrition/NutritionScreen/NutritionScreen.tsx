import { SafeAreaView } from 'react-native-safe-area-context';
import React, { useState, useRef, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  TextInput,
  Animated,
  PanResponder,
  Dimensions,
  ActivityIndicator,
  Alert
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useRoute, RouteProp, useNavigation } from '@react-navigation/native';
import { MainTabParamList, RootStackParamList } from '../../../navigation/AppNavigator';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { theme } from '../../../theme';
import { nutritionService, NutritionData } from '../../../services/api/nutritionService';

const { width: SCREEN_WIDTH, height: SCREEN_HEIGHT } = Dimensions.get('window');

type NutritionRouteProp = RouteProp<MainTabParamList, 'Nutrition'>;

export const NutritionScreen = () => {
  const route = useRoute<NutritionRouteProp>();
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const userId = route.params?.userId || 1;
  
  const [loading, setLoading] = useState(true);
  const [nutritionData, setNutritionData] = useState<NutritionData | null>(null);

  // Draggable button state
  const pan = useRef(new Animated.ValueXY({ x: SCREEN_WIDTH - 84, y: SCREEN_HEIGHT - 240 })).current;
  const [isDragging, setIsDragging] = useState(false);

  const panResponder = useRef(
    PanResponder.create({
      onStartShouldSetPanResponder: () => true,
      onMoveShouldSetPanResponder: () => true,
      onPanResponderGrant: () => {
        setIsDragging(true);
        pan.setOffset({
          x: (pan.x as any)._value,
          y: (pan.y as any)._value,
        });
        pan.setValue({ x: 0, y: 0 });
      },
      onPanResponderMove: Animated.event([null, { dx: pan.x, dy: pan.y }], {
        useNativeDriver: false,
      }),
      onPanResponderRelease: (_, gesture) => {
        setIsDragging(false);
        pan.flattenOffset();

        // Get current position
        let finalX = (pan.x as any)._value;
        let finalY = (pan.y as any)._value;

        // Constrain to screen bounds
        const buttonSize = 64;
        const padding = 20;
        
        finalX = Math.max(padding, Math.min(finalX, SCREEN_WIDTH - buttonSize - padding));
        finalY = Math.max(padding, Math.min(finalY, SCREEN_HEIGHT - buttonSize - padding - 80)); // 80 for tab bar

        // Animate to final position
        Animated.spring(pan, {
          toValue: { x: finalX, y: finalY },
          useNativeDriver: false,
          tension: 50,
          friction: 7,
        }).start();
      },
    })
  ).current;

  const fetchNutritionData = async () => {
    try {
      setLoading(true);
      const data = await nutritionService.getNutritionData(userId);
      setNutritionData(data);
    } catch (error) {
      console.error('Fetch error:', error);
      Alert.alert('Error', 'Failed to load nutrition data');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchNutritionData();
  }, [userId]);

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
      case 'breakfast': return '#F59E0B'; // Orange
      case 'lunch': return '#10B981'; // Green
      case 'dinner': return '#6366F1'; // Blue
      case 'snack': return '#EC4899'; // Pink
      default: return '#9CA3AF';
    }
  };

  const formatDate = () => {
    const options: Intl.DateTimeFormatOptions = { day: '2-digit', month: 'short' };
    return `TODAY, ${new Date().toLocaleDateString('en-GB', options).toUpperCase()}`;
  };

  const caloriesGoal = nutritionData?.goals.calories || 2000;
  const totalCalories = nutritionData?.totals.calories || 0;
  const caloriesRemaining = nutritionData?.remaining.calories || caloriesGoal;
  
  const proteinCurrent = nutritionData?.totals.protein || 0;
  const proteinGoal = nutritionData?.goals.protein || 160;
  
  const carbsCurrent = nutritionData?.totals.carbs || 0;
  const carbsGoal = nutritionData?.goals.carbs || 220;
  
  const fatsCurrent = nutritionData?.totals.fats || 0;
  const fatsGoal = nutritionData?.goals.fats || 65;

  const progress = (totalCalories / caloriesGoal) * 100;

  if (loading) {
    return (
      <SafeAreaView style={[styles.container, { justifyContent: 'center', alignItems: 'center' }]}>
        <ActivityIndicator size="large" color="#10B981" />
        <Text style={{ marginTop: 10, color: '#9CA3AF' }}>Loading nutrition data...</Text>
      </SafeAreaView>
    );
  }

  const MacroBar = ({ label, current, goal, color }: { label: string, current: number, goal: number, color: string }) => {
    const percentage = Math.min((current / goal) * 100, 100);
    return (
      <View style={styles.macroItem}>
        <View style={styles.macroHeader}>
          <Text style={styles.macroLabel}>{label.toUpperCase()}</Text>
          <Text style={styles.macroValue}>{current}g / {goal}g</Text>
        </View>
        <View style={styles.progressBarContainer}>
          <View style={[styles.progressBarFill, { width: `${percentage}%`, backgroundColor: color }]} />
        </View>
      </View>
    );
  };

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView 
        style={styles.scrollView}
        contentContainerStyle={styles.scrollContent}
        showsVerticalScrollIndicator={false}
      >
        {/* Header */}
        <View style={styles.header}>
          <Text style={styles.headerTitle}>
            Nutrition <Text style={styles.headerTitleGreen}>Log</Text>
          </Text>
        </View>

        {/* Calories Card */}
        <View style={styles.caloriesCard}>
          <View style={styles.caloriesHeader}>
            <View>
              <Text style={styles.caloriesLabel}>CALORIES REMAINING</Text>
              <View style={styles.caloriesValueContainer}>
                <Text style={styles.caloriesValue}>{caloriesRemaining}</Text>
                <Text style={styles.caloriesUnit}>kcal</Text>
              </View>
            </View>
            <View style={styles.circularProgressContainer}>
              <View style={[styles.circularProgress, { borderColor: progress > 100 ? '#EF4444' : '#10B981' }]}>
                <Ionicons name="restaurant" size={24} color={progress > 100 ? '#EF4444' : '#10B981'} />
              </View>
            </View>
          </View>

          <View style={styles.macrosContainer}>
            <MacroBar label="Protein" current={proteinCurrent} goal={proteinGoal} color="#3B82F6" />
            <MacroBar label="Carbs" current={carbsCurrent} goal={carbsGoal} color="#F59E0B" />
            <MacroBar label="Fats" current={fatsCurrent} goal={fatsGoal} color="#EF4444" />
          </View>
        </View>

        {/* Search Bar */}
        {/* <View style={styles.searchContainer}>
          <Ionicons name="search" size={20} color="#9CA3AF" />
          <TextInput
            style={styles.searchInput}
            placeholder="Search food or brand..."
            placeholderTextColor="#9CA3AF"
          />
        </View> */}

        {/* Scan Button */}
        <View style={styles.scanContainer}>
          <TouchableOpacity 
            style={styles.scanButton} 
            activeOpacity={0.8}
            onPress={() => navigation.navigate('FoodScan', { userId })}
          >
            <View style={styles.scanButtonContent}>
              <View style={styles.scanIconWrapper}>
                <Ionicons name="scan-outline" size={24} color="#fff" />
              </View>
              <Text style={styles.scanButtonText}>Scan Meal with AI Camera</Text>
              <Ionicons name="sparkles" size={18} color="#10B981" />
            </View>
          </TouchableOpacity>
        </View>

        {/* Logged Meals Section */}
        <View style={styles.loggedMealsHeader}>
          <Text style={styles.loggedMealsTitle}>LOGGED MEALS</Text>
          <Text style={styles.loggedMealsDate}>{formatDate()}</Text>
        </View>

        {/* Meals List */}
        {nutritionData?.meals.length === 0 ? (
          <View style={styles.emptyState}>
            <View style={styles.emptyIconCircle}>
              <Ionicons name="restaurant-outline" size={40} color="#9CA3AF" />
            </View>
            <Text style={styles.emptyText}>No meals logged today</Text>
            <Text style={styles.emptySubtext}>Scan your first meal to start tracking!</Text>
          </View>
        ) : (
          <>
            {nutritionData?.meals.slice(0, 3).map((meal) => (
              <View key={meal.id} style={styles.mealCard}>
                <View style={styles.mealHeader}>
                  <View style={styles.mealHeaderLeft}>
                    <View style={[styles.mealIconContainer, { backgroundColor: getMealColor(meal.meal_type) }]}>
                      <Ionicons name={getMealIcon(meal.meal_type) as any} size={20} color="#fff" />
                    </View>
                    <View style={{ flex: 1 }}>
                      <Text style={styles.mealName} numberOfLines={1}>
                        {meal.meal_name}
                      </Text>
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
                    <Text style={styles.macroPillValue}>{meal.protein || 24}g</Text>
                  </View>
                  <View style={styles.macroPill}>
                    <Text style={[styles.macroPillLabel, { color: '#F59E0B' }]}>C</Text>
                    <Text style={styles.macroPillValue}>{meal.carbs || 45}g</Text>
                  </View>
                  <View style={styles.macroPill}>
                    <Text style={[styles.macroPillLabel, { color: '#EF4444' }]}>F</Text>
                    <Text style={styles.macroPillValue}>{meal.fats || 12}g</Text>
                  </View>
                </View>
              </View>
            ))}

            {(nutritionData?.meals.length || 0) > 3 && (
              <TouchableOpacity 
                style={styles.viewMoreButton}
                onPress={() => navigation.navigate('NutritionHistory', { userId })}
              >
                <Text style={styles.viewMoreText}>
                  History ({(nutritionData?.meals.length || 0) - 3} more)
                </Text>
                <Ionicons name="chevron-forward" size={18} color="#10B981" />
              </TouchableOpacity>
            )}
          </>
        )}

        {/* Add Button Spacing */}
        <View style={styles.bottomSpacer} />
      </ScrollView>

      {/* Floating Add Button - Draggable */}
      <Animated.View
        style={[
          styles.addButton,
          {
            transform: [
              { translateX: pan.x },
              { translateY: pan.y },
              { scale: isDragging ? 1.1 : 1 },
            ],
            opacity: isDragging ? 0.8 : 1,
          },
        ]}
        {...panResponder.panHandlers}
      >
        <TouchableOpacity style={styles.addButtonInner} activeOpacity={0.8}>
          <Ionicons name="add" size={32} color="#FFFFFF" />
        </TouchableOpacity>
      </Animated.View>
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
    paddingBottom: 160,
  },
  header: {
    marginBottom: 20,
  },
  headerTitle: {
    fontSize: 32,
    fontWeight: 'bold',
    color: '#1F2937',
  },
  headerTitleGreen: {
    color: '#10B981',
  },
  caloriesCard: {
    backgroundColor: '#1F2937',
    borderRadius: 24,
    padding: 24,
    marginBottom: 20,
  },
  caloriesHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 24,
  },
  caloriesLabel: {
    fontSize: 12,
    fontWeight: '600',
    color: '#9CA3AF',
    letterSpacing: 1,
    marginBottom: 8,
  },
  caloriesValueContainer: {
    flexDirection: 'row',
    alignItems: 'baseline',
  },
  caloriesValue: {
    fontSize: 56,
    fontWeight: 'bold',
    color: '#FFFFFF',
    fontStyle: 'italic',
  },
  caloriesUnit: {
    fontSize: 20,
    fontWeight: '600',
    color: '#9CA3AF',
    marginLeft: 4,
  },
  macrosContainer: {
    gap: 16,
  },
  macroItem: {
    gap: 8,
  },
  macroHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  macroLabel: {
    fontSize: 12,
    fontWeight: '800',
    color: '#9CA3AF',
    letterSpacing: 1,
  },
  macroValue: {
    fontSize: 14,
    fontWeight: '700',
    color: '#FFFFFF',
  },
  progressBarContainer: {
    height: 8,
    backgroundColor: 'rgba(255,255,255,0.1)',
    borderRadius: 4,
    overflow: 'hidden',
  },
  progressBarFill: {
    height: '100%',
    borderRadius: 4,
  },
  searchContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#F3F4F6',
    borderRadius: 16,
    paddingHorizontal: 16,
    paddingVertical: 14,
    marginBottom: 16,
  },
  searchInput: {
    flex: 1,
    marginLeft: 12,
    fontSize: 16,
    color: '#1F2937',
  },
  scanContainer: {
    marginBottom: 32,
    shadowColor: '#10B981',
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.15,
    shadowRadius: 20,
    elevation: 8,
  },
  scanButton: {
    backgroundColor: '#FFFFFF',
    borderRadius: 20,
    overflow: 'hidden',
    borderWidth: 1.5,
    borderColor: '#D1FAE5',
  },
  scanButtonContent: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 16,
    gap: 16,
  },
  scanIconWrapper: {
    width: 48,
    height: 48,
    borderRadius: 16,
    backgroundColor: '#10B981',
    justifyContent: 'center',
    alignItems: 'center',
  },
  scanButtonText: {
    flex: 1,
    fontSize: 17,
    fontWeight: '700',
    color: '#1F2937',
  },
  loggedMealsHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 20,
    paddingHorizontal: 4,
  },
  loggedMealsTitle: {
    fontSize: 14,
    fontWeight: '800',
    color: '#9CA3AF',
    letterSpacing: 1.5,
  },
  loggedMealsDate: {
    fontSize: 13,
    fontWeight: '700',
    color: '#10B981',
  },
  emptyState: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 60,
    backgroundColor: '#F9FAFB',
    borderRadius: 30,
    borderWidth: 2,
    borderColor: '#F3F4F6',
    borderStyle: 'dashed',
  },
  emptyIconCircle: {
    width: 80,
    height: 80,
    borderRadius: 40,
    backgroundColor: '#fff',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 20,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.05,
    shadowRadius: 10,
    elevation: 2,
  },
  emptyText: {
    fontSize: 18,
    fontWeight: '700',
    color: '#374151',
    marginBottom: 8,
  },
  emptySubtext: {
    fontSize: 14,
    color: '#9CA3AF',
    textAlign: 'center',
  },
  mealCard: {
    backgroundColor: '#FFFFFF',
    borderRadius: 24,
    padding: 20,
    marginBottom: 20,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.04,
    shadowRadius: 15,
    elevation: 3,
    borderWidth: 1,
    borderColor: '#F3F4F6',
  },
  mealHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 20,
  },
  mealHeaderLeft: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
    gap: 14,
  },
  mealIconContainer: {
    width: 52,
    height: 52,
    borderRadius: 18,
    justifyContent: 'center',
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.1,
    shadowRadius: 8,
    elevation: 4,
  },
  mealName: {
    fontSize: 19,
    fontWeight: '800',
    color: '#111827',
    marginBottom: 2,
  },
  mealTime: {
    fontSize: 14,
    fontWeight: '600',
    color: '#9CA3AF',
  },
  mealCaloriesContainer: {
    alignItems: 'flex-end',
  },
  mealCalories: {
    fontSize: 26,
    fontWeight: '900',
    color: '#111827',
  },
  mealCaloriesUnit: {
    fontSize: 12,
    fontWeight: '700',
    color: '#9CA3AF',
    marginTop: -2,
    textTransform: 'uppercase',
  },
  circularProgressContainer: {
    width: 72,
    height: 72,
    justifyContent: 'center',
    alignItems: 'center',
  },
  circularProgress: {
    width: 72,
    height: 72,
    borderRadius: 36,
    borderWidth: 6,
    justifyContent: 'center',
    alignItems: 'center',
  },
  macroSummary: {
    flexDirection: 'row',
    gap: 10,
  },
  macroPill: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#F3F4F6',
    paddingVertical: 8,
    borderRadius: 12,
    gap: 6,
  },
  macroPillLabel: {
    fontSize: 13,
    fontWeight: '900',
  },
  macroPillValue: {
    fontSize: 13,
    fontWeight: '700',
    color: '#374151',
  },
  bottomSpacer: {
    height: 40,
  },
  viewMoreButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 12,
    backgroundColor: '#F0FDF4',
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#DCFCE7',
    marginBottom: 16,
    gap: 8,
  },
  viewMoreText: {
    fontSize: 14,
    fontWeight: '700',
    color: '#10B981',
  },
  addButton: {
    position: 'absolute',
    width: 64,
    height: 64,
    zIndex: 1000,
  },
  addButtonInner: {
    width: 64,
    height: 64,
    borderRadius: 32,
    backgroundColor: '#10b98198',
    justifyContent: 'center',
    alignItems: 'center',
    shadowColor: '#10B981',
    shadowOffset: { width: 0, height: 8 },
    shadowOpacity: 0.4,
    shadowRadius: 16,
    elevation: 8,
  },
});
