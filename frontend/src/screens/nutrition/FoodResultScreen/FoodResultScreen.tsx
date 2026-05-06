import React, { useState, useEffect } from 'react';
import {
  StyleSheet,
  Text,
  View,
  TouchableOpacity,
  ScrollView,
  Image,
  Dimensions,
  Alert,
  ActivityIndicator,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { useNavigation, useRoute, RouteProp } from '@react-navigation/native';
import { theme } from '../../../theme';
import { nutritionService } from '../../../services/api/nutritionService';
import * as Animatable from 'react-native-animatable';

const { width } = Dimensions.get('window');

type FoodResultRouteProp = RouteProp<{
  FoodResult: { imageUri: string; base64: string; userId: number };
}, 'FoodResult'>;

export const FoodResultScreen = () => {
  const navigation = useNavigation<any>();
  const route = useRoute<FoodResultRouteProp>();
  const { imageUri, base64, userId } = route.params;
  
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [result, setResult] = useState<any>(null);

  useEffect(() => {
    // Call the real scan API
    handleScan();
  }, []);

  const handleScan = async () => {
    try {
      setLoading(true);
      // Call the real scan API
      const data = await nutritionService.scanMeal(base64);
      setResult(data);
      setLoading(false);
    } catch (error) {
      console.error('Scan error:', error);
      Alert.alert("Error", "Could not identify meal. Please try again.");
      navigation.goBack();
    }
  };

  const getMealType = () => {
    const hour = new Date().getHours();
    if (hour >= 5 && hour < 11) return 'Breakfast';
    if (hour >= 11 && hour < 16) return 'Lunch';
    if (hour >= 16 && hour < 22) return 'Dinner';
    return 'Snack';
  };

  const handleSave = async () => {
    try {
      setSaving(true);
      // Real API call to save the meal
      await nutritionService.logMeal({
        user_id: userId,
        meal_name: result.meal_name,
        calories: result.calories,
        protein: result.protein,
        carbs: result.carbs,
        fats: result.fats,
        meal_type: getMealType(),
      });
      
      Alert.alert("Success", "Meal logged successfully!", [
        { 
          text: "OK", 
          onPress: () => {
            navigation.reset({
              index: 0,
              routes: [{ 
                name: 'Main', 
                params: { 
                  screen: 'Nutrition',
                  params: { userId: userId }
                } 
              }],
            });
          }
        }
      ]);
    } catch (error) {
      console.error('Save error:', error);
      Alert.alert("Error", "Failed to save meal.");
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color={theme.colors.primary} />
        <Text style={styles.loadingText}>Processing results...</Text>
      </View>
    );
  }

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView showsVerticalScrollIndicator={false}>
        {/* Header Image */}
        <View style={styles.imageContainer}>
          <Image source={{ uri: imageUri }} style={styles.image} />
          <TouchableOpacity 
            style={styles.backButton}
            onPress={() => navigation.goBack()}
          >
            <Ionicons name="arrow-back" size={24} color="#fff" />
          </TouchableOpacity>
        </View>

        <Animatable.View animation="fadeInUp" duration={800} style={styles.content}>
          <View style={styles.titleSection}>
            <Text style={styles.title}>{result.meal_name}</Text>
            <View style={styles.caloriesBadge}>
              <Text style={styles.caloriesValue}>{result.calories}</Text>
              <Text style={styles.caloriesLabel}>kcal</Text>
            </View>
          </View>

          {/* Macros Row */}
          <View style={styles.macrosRow}>
            <View style={[styles.macroItem, { backgroundColor: '#EEF2FF' }]}>
              <Text style={[styles.macroLabel, { color: '#4F46E5' }]}>PROTEIN</Text>
              <Text style={styles.macroValue}>{result.protein}g</Text>
            </View>
            <View style={[styles.macroItem, { backgroundColor: '#FFFBEB' }]}>
              <Text style={[styles.macroLabel, { color: '#D97706' }]}>CARBS</Text>
              <Text style={styles.macroValue}>{result.carbs}g</Text>
            </View>
            <View style={[styles.macroItem, { backgroundColor: '#FEF2F2' }]}>
              <Text style={[styles.macroLabel, { color: '#DC2626' }]}>FATS</Text>
              <Text style={styles.macroValue}>{result.fats}g</Text>
            </View>
          </View>

          <View style={styles.divider} />

          <Text style={styles.sectionTitle}>Identified Ingredients</Text>
          {result.items.map((item: any, index: number) => (
            <View key={index} style={styles.ingredientItem}>
              <View>
                <Text style={styles.ingredientName}>{item.name}</Text>
                <Text style={styles.ingredientAmount}>{item.amount}</Text>
              </View>
              <Text style={styles.ingredientCalories}>{item.calories} kcal</Text>
            </View>
          ))}

          <View style={styles.bottomSpacing} />
        </Animatable.View>
      </ScrollView>

      {/* Action Buttons */}
      <View style={styles.footer}>
        <TouchableOpacity 
          style={styles.retakeButton} 
          onPress={() => navigation.goBack()}
        >
          <Text style={styles.retakeButtonText}>Retake</Text>
        </TouchableOpacity>
        <TouchableOpacity 
          style={styles.saveButton} 
          onPress={handleSave}
          disabled={saving}
        >
          {saving ? (
            <ActivityIndicator color="#fff" />
          ) : (
            <>
              <Ionicons name="checkmark-circle" size={20} color="#fff" />
              <Text style={styles.saveButtonText}>Confirm & Log</Text>
            </>
          )}
        </TouchableOpacity>
      </View>
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#fff',
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#fff',
  },
  loadingText: {
    marginTop: 16,
    color: '#6B7280',
    fontSize: 16,
  },
  imageContainer: {
    width: width,
    height: 300,
    position: 'relative',
  },
  image: {
    width: '100%',
    height: '100%',
  },
  backButton: {
    position: 'absolute',
    top: 50,
    left: 20,
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: 'rgba(0,0,0,0.3)',
    justifyContent: 'center',
    alignItems: 'center',
  },
  content: {
    flex: 1,
    backgroundColor: '#fff',
    borderTopLeftRadius: 30,
    borderTopRightRadius: 30,
    marginTop: -30,
    padding: 24,
  },
  titleSection: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 24,
  },
  title: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#111827',
    flex: 1,
  },
  caloriesBadge: {
    backgroundColor: '#D1FAE5',
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 12,
    alignItems: 'center',
  },
  caloriesValue: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#065F46',
  },
  caloriesLabel: {
    fontSize: 10,
    color: '#065F46',
    fontWeight: '600',
  },
  macrosRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 24,
  },
  macroItem: {
    width: (width - 48 - 24) / 3,
    padding: 12,
    borderRadius: 16,
    alignItems: 'center',
  },
  macroLabel: {
    fontSize: 10,
    fontWeight: 'bold',
    marginBottom: 4,
  },
  macroValue: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#111827',
  },
  divider: {
    height: 1,
    backgroundColor: '#F3F4F6',
    marginBottom: 24,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#111827',
    marginBottom: 16,
  },
  ingredientItem: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#F3F4F6',
  },
  ingredientName: {
    fontSize: 16,
    fontWeight: '500',
    color: '#374151',
  },
  ingredientAmount: {
    fontSize: 14,
    color: '#9CA3AF',
  },
  ingredientCalories: {
    fontSize: 14,
    fontWeight: '600',
    color: '#111827',
  },
  bottomSpacing: {
    height: 100,
  },
  footer: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    padding: 24,
    backgroundColor: '#fff',
    flexDirection: 'row',
    gap: 12,
    borderTopWidth: 1,
    borderTopColor: '#F3F4F6',
  },
  retakeButton: {
    flex: 1,
    paddingVertical: 16,
    borderRadius: 16,
    backgroundColor: '#F3F4F6',
    alignItems: 'center',
  },
  retakeButtonText: {
    color: '#4B5563',
    fontWeight: '600',
    fontSize: 16,
  },
  saveButton: {
    flex: 2,
    paddingVertical: 16,
    borderRadius: 16,
    backgroundColor: '#10B981',
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
    gap: 8,
  },
  saveButtonText: {
    color: '#fff',
    fontWeight: 'bold',
    fontSize: 16,
  },
});
