import React, { useState, useRef } from 'react';
import {
  View,
  Text,
  StyleSheet,
  SafeAreaView,
  ScrollView,
  TouchableOpacity,
  TextInput,
  Animated,
  PanResponder,
  Dimensions,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { theme } from '../../../theme';

const { width: SCREEN_WIDTH, height: SCREEN_HEIGHT } = Dimensions.get('window');

interface Meal {
  id: string;
  name: string;
  time: string;
  icon: string;
  calories: number;
  items: FoodItem[];
}

interface FoodItem {
  name: string;
  amount: string;
  calories: number;
}

export const NutritionScreen = () => {
  const [meals] = useState<Meal[]>([
    {
      id: '1',
      name: 'Breakfast',
      time: '8:30 AM',
      icon: 'sunny',
      calories: 420,
      items: [
        { name: 'Greek Yogurt with Berries', amount: '250g', calories: 185 },
        { name: 'Black Coffee', amount: '1 cup', calories: 2 },
        { name: 'Almonds', amount: '20g', calories: 233 },
      ],
    },
    {
      id: '2',
      name: 'Lunch',
      time: '12:30 PM',
      icon: 'partly-sunny',
      calories: 820,
      items: [],
    },
  ]);

  // Draggable button state
  const pan = useRef(new Animated.ValueXY({ x: SCREEN_WIDTH - 84, y: SCREEN_HEIGHT - 180 })).current;
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

  const caloriesRemaining = 560;
  const caloriesGoal = 2000;
  const proteinCurrent = 124;
  const proteinGoal = 160;
  const carbsCurrent = 185;
  const carbsGoal = 220;
  const fatsCurrent = 42;
  const fatsGoal = 65;

  const totalCalories = meals.reduce((sum, meal) => sum + meal.calories, 0);
  const progress = (totalCalories / caloriesGoal) * 100;

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
            <View style={styles.circularProgress}>
              <Ionicons name="restaurant" size={28} color="#10B981" />
            </View>
          </View>

          {/* Macros */}
          <View style={styles.macrosContainer}>
            {/* Protein */}
            <View style={styles.macroRow}>
              <Text style={styles.macroLabel}>PROTEIN</Text>
              <Text style={styles.macroValue}>
                {proteinCurrent}G / {proteinGoal}G
              </Text>
            </View>
            <View style={styles.progressBar}>
              <View 
                style={[
                  styles.progressFill, 
                  styles.progressProtein,
                  { width: `${(proteinCurrent / proteinGoal) * 100}%` }
                ]} 
              />
            </View>

            {/* Carbs */}
            <View style={styles.macroRow}>
              <Text style={styles.macroLabel}>CARBS</Text>
              <Text style={styles.macroValue}>
                {carbsCurrent}G / {carbsGoal}G
              </Text>
            </View>
            <View style={styles.progressBar}>
              <View 
                style={[
                  styles.progressFill, 
                  styles.progressCarbs,
                  { width: `${(carbsCurrent / carbsGoal) * 100}%` }
                ]} 
              />
            </View>

            {/* Fats */}
            <View style={styles.macroRow}>
              <Text style={styles.macroLabel}>FATS</Text>
              <Text style={styles.macroValue}>
                {fatsCurrent}G / {fatsGoal}G
              </Text>
            </View>
            <View style={styles.progressBar}>
              <View 
                style={[
                  styles.progressFill, 
                  styles.progressFats,
                  { width: `${(fatsCurrent / fatsGoal) * 100}%` }
                ]} 
              />
            </View>
          </View>
        </View>

        {/* Search Bar */}
        <View style={styles.searchContainer}>
          <Ionicons name="search" size={20} color="#9CA3AF" />
          <TextInput
            style={styles.searchInput}
            placeholder="Search food or brand..."
            placeholderTextColor="#9CA3AF"
          />
        </View>

        {/* Scan Button */}
        <TouchableOpacity style={styles.scanButton}>
          <Ionicons name="scan" size={24} color="#10B981" />
          <Text style={styles.scanButtonText}>Scan Meal with Camera</Text>
          <Ionicons name="sparkles" size={20} color="#10B981" />
        </TouchableOpacity>

        {/* Logged Meals Section */}
        <View style={styles.loggedMealsHeader}>
          <Text style={styles.loggedMealsTitle}>LOGGED MEALS</Text>
          <Text style={styles.loggedMealsDate}>TODAY, 24 OCT</Text>
        </View>

        {/* Meals List */}
        {meals.map((meal) => (
          <View key={meal.id} style={styles.mealCard}>
            <View style={styles.mealHeader}>
              <View style={styles.mealHeaderLeft}>
                <View style={styles.mealIconContainer}>
                  <Ionicons name={meal.icon as any} size={24} color="#10B981" />
                </View>
                <View>
                  <Text style={styles.mealName}>{meal.name}</Text>
                  <Text style={styles.mealTime}>{meal.time}</Text>
                </View>
              </View>
              <Text style={styles.mealCalories}>
                {meal.calories}
                <Text style={styles.mealCaloriesUnit}>kcal</Text>
              </Text>
            </View>

            {/* Food Items */}
            {meal.items.map((item, index) => (
              <View key={index} style={styles.foodItem}>
                <Text style={styles.foodItemName}>{item.name}</Text>
                <Text style={styles.foodItemDetails}>
                  {item.amount} • {item.calories} kcal
                </Text>
              </View>
            ))}
          </View>
        ))}

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
    paddingBottom: 100,
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
  circularProgress: {
    width: 60,
    height: 60,
    borderRadius: 30,
    borderWidth: 4,
    borderColor: '#10B981',
    borderTopColor: 'transparent',
    borderLeftColor: 'transparent',
    justifyContent: 'center',
    alignItems: 'center',
    transform: [{ rotate: '45deg' }],
  },
  macrosContainer: {
    gap: 16,
  },
  macroRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 6,
  },
  macroLabel: {
    fontSize: 11,
    fontWeight: '700',
    letterSpacing: 1,
    color:'#ffff'
  },
  macroValue: {
    fontSize: 13,
    fontWeight: '600',
    color: '#E5E7EB',
  },
  progressBar: {
    height: 6,
    backgroundColor: '#374151',
    borderRadius: 3,
    overflow: 'hidden',
  },
  progressFill: {
    height: '100%',
    borderRadius: 3,
  },
  progressProtein: {
    backgroundColor: '#3B82F6',
  },
  progressCarbs: {
    backgroundColor: '#F59E0B',
  },
  progressFats: {
    backgroundColor: '#EF4444',
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
  scanButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#D1FAE5',
    borderRadius: 16,
    paddingVertical: 18,
    marginBottom: 24,
    gap: 8,
  },
  scanButtonText: {
    fontSize: 16,
    fontWeight: '600',
    color: '#065F46',
  },
  loggedMealsHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 16,
  },
  loggedMealsTitle: {
    fontSize: 12,
    fontWeight: '700',
    color: '#9CA3AF',
    letterSpacing: 1,
  },
  loggedMealsDate: {
    fontSize: 12,
    fontWeight: '600',
    color: '#10B981',
  },
  mealCard: {
    backgroundColor: '#FFFFFF',
    borderRadius: 20,
    padding: 20,
    marginBottom: 16,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 8,
    elevation: 2,
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
    gap: 12,
  },
  mealIconContainer: {
    width: 48,
    height: 48,
    borderRadius: 24,
    backgroundColor: '#D1FAE5',
    justifyContent: 'center',
    alignItems: 'center',
  },
  mealName: {
    fontSize: 18,
    fontWeight: '700',
    color: '#1F2937',
  },
  mealTime: {
    fontSize: 14,
    color: '#9CA3AF',
    marginTop: 2,
  },
  mealCalories: {
    fontSize: 28,
    fontWeight: 'bold',
    color: '#1F2937',
  },
  mealCaloriesUnit: {
    fontSize: 14,
    fontWeight: '500',
    color: '#9CA3AF',
  },
  foodItem: {
    paddingVertical: 8,
    borderTopWidth: 1,
    borderTopColor: '#F3F4F6',
  },
  foodItemName: {
    fontSize: 15,
    fontWeight: '500',
    color: '#1F2937',
    marginBottom: 4,
  },
  foodItemDetails: {
    fontSize: 13,
    color: '#9CA3AF',
  },
  bottomSpacer: {
    height: 20,
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
