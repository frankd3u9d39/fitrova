import { SafeAreaView } from 'react-native-safe-area-context';
import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  TextInput,
  ActivityIndicator,
  ImageBackground,
  Alert,
  Modal
} from 'react-native';

import { Ionicons } from '@expo/vector-icons';
import { useRoute, RouteProp, useNavigation, useFocusEffect } from '@react-navigation/native';
import { MainTabParamList, RootStackParamList } from '../../../navigation/types';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { theme } from '../../../theme';
import { nutritionService, NutritionData, AIFoodRecommendation, Meal } from '../../../services/api/nutritionService';
import * as Animatable from 'react-native-animatable';
import { Notification } from '../../../services/api/notificationService';
import { AICoachModal } from '../../../components/common/AICoachModal';
import { SubscriptionUpgradeModal } from '../../../components/common/SubscriptionUpgradeModal';
import AsyncStorage from '@react-native-async-storage/async-storage';
import LottieView from 'lottie-react-native';



type NutritionRouteProp = RouteProp<MainTabParamList, 'Nutrition'>;

export const NutritionScreen = () => {
  const route = useRoute<NutritionRouteProp>();
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const userId = route.params?.userId || 1;
  
  const [loading, setLoading] = useState(true);
  const [nutritionData, setNutritionData] = useState<NutritionData | null>(null);
  const [aiRecommendations, setAiRecommendations] = useState<AIFoodRecommendation[]>([]);
  const [aiLoading, setAiLoading] = useState(true);
  const [selectedRec, setSelectedRec] = useState<AIFoodRecommendation | null>(null);
  const [modalVisible, setModalVisible] = useState(false);
  const [loggingMeal, setLoggingMeal] = useState(false);
  const [selectedMealType, setSelectedMealType] = useState<'breakfast' | 'lunch' | 'dinner' | 'snack'>('snack');
  
  // Notification states
  const [latestNotification, setLatestNotification] = useState<Notification | null>(null);
  const [showAIModal, setShowAIModal] = useState(false);
  const [paywallVisible, setPaywallVisible] = useState(false);
  const [paywallData, setPaywallData] = useState<any>(null);
  const [isLocked, setIsLocked] = useState(false);
  const [firstName, setFirstName] = useState<string>('Athlete');
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
    inputBg: darkTheme ? '#0F172A' : '#F9FAFB',
    inputBorder: darkTheme ? '#334155' : '#E5E7EB',
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

  const getDefaultMealType = (): 'breakfast' | 'lunch' | 'dinner' | 'snack' => {
    const hour = new Date().getHours();
    if (hour >= 5 && hour < 11) return 'breakfast';
    if (hour >= 11 && hour < 16) return 'lunch';
    if (hour >= 16 && hour < 21) return 'dinner';
    return 'snack';
  };

  const handleSelectRecommendation = (rec: AIFoodRecommendation) => {
    setSelectedRec(rec);
    setSelectedMealType(getDefaultMealType());
    setModalVisible(true);
  };

  const handleLogRecommendedMeal = async () => {
    if (!selectedRec) return;
    try {
      setLoggingMeal(true);
      const payload = {
        user_id: userId,
        meal_name: selectedRec.name,
        calories: selectedRec.calories,
        protein: selectedRec.protein,
        carbs: selectedRec.carbs,
        fats: selectedRec.fats,
        meal_type: selectedMealType,
      };
      await nutritionService.logMeal(payload);
      await fetchNutritionData();
      Alert.alert('Success', `"${selectedRec.name}" has been logged to your ${selectedMealType}!`);
      setModalVisible(false);
      setSelectedRec(null);
    } catch (error) {
      console.error('Log recommended meal error:', error);
      Alert.alert('Error', 'Failed to log the recommended meal.');
    } finally {
      setLoggingMeal(false);
    }
  };

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

  const handleDismissInsight = async () => {
    if (!nutritionData?.latest_insight) return;
    try {
      setNutritionData(prev => prev ? { ...prev, latest_insight: null } : null);
      await nutritionService.dismissInsight(userId);
    } catch (error) {
      console.error('Dismiss insight error:', error);
      fetchNutritionData();
    }
  };

  const fetchAIRecommendations = async () => {
    try {
      setAiLoading(true);
      setIsLocked(false);
      const data = await nutritionService.getAIFoodRecommendations(userId);
      setAiRecommendations(data);
    } catch (error: any) {
      if (error && (error.status === 'subscription_locked' || error.statusCode === 403)) {
        console.log('AI Recommendations locked (Expected):', error.message || 'Trial used.');
        setIsLocked(true);
        setPaywallData(error);
      } else {
        console.error('AI Fetch error:', error);
      }
    } finally {
      setAiLoading(false);
    }
  };

  useEffect(() => {
    const loadUserSession = async () => {
      try {
        const savedSession = await AsyncStorage.getItem('user_session');
        if (savedSession) {
          const session = JSON.parse(savedSession);
          if (session.firstName) {
            setFirstName(session.firstName);
          }
        }
      } catch (e) {
        console.error('Error loading session in NutritionScreen:', e);
      }
    };
    loadUserSession();
    fetchNutritionData();
    fetchAIRecommendations();
  }, [userId]);

  useEffect(() => {
    if (nutritionData?.latest_insight) {
      setLatestNotification({
        id: 0,
        insight_text: nutritionData.latest_insight.text,
        insight_type: nutritionData.latest_insight.type as any || 'tip',
        is_read: false,
        created_at: new Date().toISOString()
      });
      setShowAIModal(true);
    } else {
      setShowAIModal(false);
      setLatestNotification(null);
    }
  }, [nutritionData?.latest_insight]);

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
  const totalConsumed = nutritionData?.totals.calories || 0;
  const totalBurned = nutritionData?.totals.burned || 0;
  
  // Net Calories = Consumed - Burned
  const netCalories = totalConsumed - totalBurned;
  
  // Remaining = Goal - Net (or more accurately: Goal + Burned - Consumed)
  const caloriesRemaining = (caloriesGoal + totalBurned) - totalConsumed;
  
  const isOverGoal = totalConsumed > (caloriesGoal + totalBurned);
  
  const proteinCurrent = nutritionData?.totals.protein || 0;
  const proteinGoal = nutritionData?.goals.protein || 160;
  
  const carbsCurrent = nutritionData?.totals.carbs || 0;
  const carbsGoal = nutritionData?.goals.carbs || 220;
  
  const fatsCurrent = nutritionData?.totals.fats || 0;
  const fatsGoal = nutritionData?.goals.fats || 65;

  const progress = (totalConsumed / (caloriesGoal + totalBurned)) * 100;

  if (loading) {
    return (
      <SafeAreaView style={[styles.container, { justifyContent: 'center', alignItems: 'center', backgroundColor: colors.background }]}>
        <LottieView
          source={require('../../../../assets/animations/watermelon.json')}
          autoPlay
          loop
          style={{ width: 120, height: 120 }}
        />
        <Text style={{ marginTop: 10, color: colors.textSecondary }}>Loading nutrition data...</Text>
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
    <SafeAreaView style={[styles.container, { backgroundColor: colors.background }]}>
      <ScrollView 
        style={[styles.scrollView, { backgroundColor: colors.background }]}
        contentContainerStyle={styles.scrollContent}
        showsVerticalScrollIndicator={false}
      >
        {/* Header */}
        <View style={styles.header}>
          <Text style={[styles.headerTitle, { color: colors.text }]}>
            Nutrition <Text style={styles.headerTitleGreen}>Log</Text>
          </Text>
        </View>

        <ImageBackground 
          source={{ uri: 'https://images.unsplash.com/photo-1490645935967-10de6ba17061?w=800&q=80' }} 
          style={styles.caloriesCard}
          imageStyle={{ borderRadius: 24 }}
        >
          <View style={styles.caloriesCardOverlay}>
            <View style={styles.caloriesHeader}>
              <View>
                <Text style={[styles.statusLabel, { color: isOverGoal ? '#EF4444' : '#10B981' }]}>
                  {isOverGoal ? 'OVER GOAL' : 'REMAINING'}
                </Text>
                <View style={styles.caloriesValueContainer}>
                  <Text style={[styles.caloriesValue, isOverGoal && { color: '#EF4444' }]}>
                    {Math.abs(caloriesRemaining)}
                  </Text>
                  <Text style={styles.caloriesUnit}>kcal</Text>
                </View>
              </View>
              <View style={styles.circularProgressContainer}>
                <View style={[styles.circularProgress, { borderColor: isOverGoal ? '#EF4444' : '#10B981' }]}>
                  <Ionicons name={isOverGoal ? "warning" : "restaurant"} size={24} color={isOverGoal ? '#EF4444' : '#10B981'} />
                </View>
              </View>
            </View>

            <View style={styles.macrosContainer}>
              <MacroBar label="Protein" current={proteinCurrent} goal={proteinGoal} color="#3B82F6" />
              <MacroBar label="Carbs" current={carbsCurrent} goal={carbsGoal} color="#F59E0B" />
              <MacroBar label="Fats" current={fatsCurrent} goal={fatsGoal} color="#EF4444" />
            </View>
          </View>
        </ImageBackground>

        {/* AI Advisor Banner has been replaced with the AICoachModal pop-up */}

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
            style={[styles.scanButton, { backgroundColor: colors.cardBg, borderColor: darkTheme ? '#334155' : '#D1FAE5' }]} 
            activeOpacity={0.8}
            onPress={() => navigation.navigate('FoodScan', { userId })}
          >
            <View style={styles.scanButtonContent}>
              <View style={styles.scanIconWrapper}>
                <Ionicons name="scan-outline" size={24} color="#fff" />
              </View>
              <Text style={[styles.scanButtonText, { color: colors.text }]}>Scan Meal with AI Camera</Text>
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
          <View style={[styles.emptyState, { backgroundColor: colors.cardBg, borderColor: colors.border }]}>
            <View style={[styles.emptyIconCircle, { backgroundColor: darkTheme ? '#334155' : '#FFFFFF' }]}>
              <Ionicons name="restaurant-outline" size={40} color="#9CA3AF" />
            </View>
            <Text style={[styles.emptyText, { color: colors.text }]}>No meals logged today</Text>
            <Text style={[styles.emptySubtext, { color: colors.textSecondary }]}>Scan your first meal to start tracking!</Text>
          </View>
        ) : (
          <>
            {nutritionData?.meals.slice(0, 3).map((meal) => (
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
                      <Text style={[styles.mealName, { color: colors.text }]} numberOfLines={1}>
                        {meal.meal_name}
                      </Text>
                      <Text style={styles.mealTime}>{meal.meal_time}</Text>
                    </View>
                  </View>
                  <View style={styles.mealCaloriesContainer}>
                    <Text style={[styles.mealCalories, { color: colors.text }]}>{meal.calories}</Text>
                    <Text style={styles.mealCaloriesUnit}>kcal</Text>
                  </View>
                </View>

                <View style={styles.macroSummary}>
                  <View style={[styles.macroPill, { backgroundColor: darkTheme ? '#334155' : '#F3F4F6' }]}>
                    <Text style={[styles.macroPillLabel, { color: '#3B82F6' }]}>P</Text>
                    <Text style={[styles.macroPillValue, { color: colors.text }]}>{meal.protein || 24}g</Text>
                  </View>
                  <View style={[styles.macroPill, { backgroundColor: darkTheme ? '#334155' : '#F3F4F6' }]}>
                    <Text style={[styles.macroPillLabel, { color: '#F59E0B' }]}>C</Text>
                    <Text style={[styles.macroPillValue, { color: colors.text }]}>{meal.carbs || 45}g</Text>
                  </View>
                  <View style={[styles.macroPill, { backgroundColor: darkTheme ? '#334155' : '#F3F4F6' }]}>
                    <Text style={[styles.macroPillLabel, { color: '#EF4444' }]}>F</Text>
                    <Text style={[styles.macroPillValue, { color: colors.text }]}>{meal.fats || 12}g</Text>
                  </View>
                </View>
              </TouchableOpacity>
            ))}

            {(nutritionData?.meals.length || 0) > 3 && (
              <TouchableOpacity 
                style={[styles.viewMoreButton, darkTheme && { backgroundColor: '#064E3B', borderColor: '#065F46' }]}
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

        {/* AI Recommendations Section */}
        <View style={styles.aiSectionHeader}>
          <Text style={styles.loggedMealsTitle}>RECOMMENDED FOR YOU ✨</Text>
          <Text style={styles.loggedMealsDate}>AI COACH</Text>
        </View>

        {aiLoading ? (
          <View style={[styles.aiLoadingContainer, { backgroundColor: colors.cardBg, borderColor: colors.border }]}>
            <ActivityIndicator size="small" color="#10B981" />
            <Text style={[styles.aiLoadingText, { color: colors.textSecondary }]}>Generating your custom menu...</Text>
          </View>
        ) : isLocked ? (
          <TouchableOpacity 
            style={[styles.aiLockedCard, { borderColor: darkTheme ? '#065F46' : '#A7F3D0', backgroundColor: darkTheme ? '#064E3B' : '#ECFDF5' }]} 
            activeOpacity={0.9}
            onPress={() => setPaywallVisible(true)}
          >
            <View style={styles.aiLockedContent}>
              <View style={[styles.aiLockedIconCircle, { backgroundColor: darkTheme ? '#1E293B' : '#FFFFFF' }]}>
                <Ionicons name="lock-closed" size={20} color="#10B981" />
              </View>
              <View style={{ flex: 1, gap: 4 }}>
                <Text style={[styles.aiLockedTitle, { color: colors.text }]}>Unlock AI Diet Recommendations</Text>
                <Text style={[styles.aiLockedDesc, { color: darkTheme ? '#A7F3D0' : '#065F46' }]}>
                  Upgrade to Advanced Premium to get personalized calorie-counted recipe suggestions tailored for your daily goals.
                </Text>
              </View>
            </View>
            <TouchableOpacity 
              style={[styles.aiLockedBtn, { backgroundColor: '#10B981' }]} 
              activeOpacity={0.8}
              onPress={() => setPaywallVisible(true)}
            >
              <Text style={styles.aiLockedBtnText}>Upgrade Now</Text>
              <Ionicons name="arrow-forward" size={14} color="#FFFFFF" />
            </TouchableOpacity>
          </TouchableOpacity>
        ) : aiRecommendations.length > 0 ? (
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.aiScrollContainer}>
            {aiRecommendations.map((rec, index) => (
              <TouchableOpacity 
                key={index} 
                style={[styles.aiCard, { backgroundColor: colors.cardBg, borderColor: darkTheme ? '#065F46' : '#D1FAE5' }]}
                activeOpacity={0.8}
                onPress={() => handleSelectRecommendation(rec)}
              >
                <View style={styles.aiCardHeader}>
                  <Text style={[styles.aiCardTitle, { color: colors.text }]} numberOfLines={2}>{rec.name}</Text>
                  <View style={[styles.aiCaloriesBadge, darkTheme && { backgroundColor: '#064E3B' }]}>
                    <Text style={styles.aiCaloriesText}>{rec.calories} kcal</Text>
                  </View>
                </View>
                <Text style={[styles.aiReasonText, { color: colors.textSecondary }]}>{rec.reason}</Text>
                
                <View style={[styles.aiMacrosRow, { backgroundColor: darkTheme ? '#334155' : '#F9FAFB' }]}>
                  <View style={styles.aiMacroItem}>
                    <Text style={[styles.aiMacroLabel, { color: '#3B82F6' }]}>Protein</Text>
                    <Text style={[styles.aiMacroValue, { color: colors.text }]}>{rec.protein}g</Text>
                  </View>
                  <View style={styles.aiMacroItem}>
                    <Text style={[styles.aiMacroLabel, { color: '#F59E0B' }]}>Carbs</Text>
                    <Text style={[styles.aiMacroValue, { color: colors.text }]}>{rec.carbs}g</Text>
                  </View>
                  <View style={styles.aiMacroItem}>
                    <Text style={[styles.aiMacroLabel, { color: '#EF4444' }]}>Fats</Text>
                    <Text style={[styles.aiMacroValue, { color: colors.text }]}>{rec.fats}g</Text>
                  </View>
                </View>
              </TouchableOpacity>
            ))}
          </ScrollView>
        ) : (
          <View style={[styles.aiLoadingContainer, { backgroundColor: colors.cardBg, borderColor: colors.border }]}>
            <Text style={[styles.emptySubtext, { color: colors.textSecondary }]}>Could not generate recommendations at this time.</Text>
          </View>
        )}

        {/* Add Button Spacing */}
        <View style={styles.bottomSpacer} />
      </ScrollView>

      {/* AI Recommendation Details Modal */}
      <Modal
        animationType="slide"
        transparent={true}
        visible={modalVisible}
        onRequestClose={() => setModalVisible(false)}
      >
        <View style={styles.modalOverlay}>
          <View style={[styles.modalContent, { backgroundColor: colors.cardBg }]}>
            {/* Modal Header */}
            <View style={[styles.modalHeader, { borderColor: colors.separator }]}>
              <View style={styles.modalTitleContainer}>
                <Text style={[styles.modalTitle, { color: colors.text }]}>{selectedRec?.name}</Text>
                {selectedRec?.prep_time && (
                  <View style={styles.prepTimeBadge}>
                    <Ionicons name="time-outline" size={14} color="#10B981" />
                    <Text style={styles.prepTimeText}>{selectedRec.prep_time}</Text>
                  </View>
                )}
              </View>
              <TouchableOpacity 
                style={[styles.closeButton, { backgroundColor: darkTheme ? '#334155' : '#F3F4F6' }]} 
                onPress={() => setModalVisible(false)}
              >
                <Ionicons name="close" size={24} color={colors.textSecondary} />
              </TouchableOpacity>
            </View>

            <ScrollView 
              style={styles.modalScrollView} 
              showsVerticalScrollIndicator={false}
              contentContainerStyle={styles.modalScrollContent}
            >
              {/* Macro Cards Grid */}
              <View style={styles.modalMacrosGrid}>
                <View style={[styles.modalMacroCard, { 
                  backgroundColor: darkTheme ? '#1E3A8A' : '#EFF6FF', 
                  borderColor: darkTheme ? '#3B82F6' : '#BFDBFE' 
                }]}>
                  <Text style={[styles.modalMacroLabel, { color: '#3B82F6' }]}>Protein</Text>
                  <Text style={[styles.modalMacroValue, { color: darkTheme ? '#EFF6FF' : '#1E3A8A' }]}>{selectedRec?.protein}g</Text>
                </View>
                <View style={[styles.modalMacroCard, { 
                  backgroundColor: darkTheme ? '#78350F' : '#FFFBEB', 
                  borderColor: darkTheme ? '#F59E0B' : '#FDE68A' 
                }]}>
                  <Text style={[styles.modalMacroLabel, { color: '#F59E0B' }]}>Carbs</Text>
                  <Text style={[styles.modalMacroValue, { color: darkTheme ? '#FFFBEB' : '#78350F' }]}>{selectedRec?.carbs}g</Text>
                </View>
                <View style={[styles.modalMacroCard, { 
                  backgroundColor: darkTheme ? '#7F1D1D' : '#FEF2F2', 
                  borderColor: darkTheme ? '#EF4444' : '#FCA5A5' 
                }]}>
                  <Text style={[styles.modalMacroLabel, { color: '#EF4444' }]}>Fats</Text>
                  <Text style={[styles.modalMacroValue, { color: darkTheme ? '#FEF2F2' : '#7F1D1D' }]}>{selectedRec?.fats}g</Text>
                </View>
                <View style={[styles.modalMacroCard, { 
                  backgroundColor: darkTheme ? '#064E3B' : '#ECFDF5', 
                  borderColor: darkTheme ? '#10B981' : '#A7F3D0' 
                }]}>
                  <Text style={[styles.modalMacroLabel, { color: '#10B981' }]}>Calories</Text>
                  <Text style={[styles.modalMacroValue, { color: darkTheme ? '#ECFDF5' : '#064E3B' }]}>{selectedRec?.calories} kcal</Text>
                </View>
              </View>

              {/* Description */}
              {selectedRec?.description && (
                <View style={styles.modalSection}>
                  <Text style={[styles.modalSectionTitle, { color: colors.text }]}>Description</Text>
                  <Text style={[styles.modalDescriptionText, { color: colors.textSecondary }]}>{selectedRec.description}</Text>
                </View>
              )}

              {/* Ingredients */}
              {selectedRec?.ingredients && selectedRec.ingredients.length > 0 && (
                <View style={styles.modalSection}>
                  <Text style={[styles.modalSectionTitle, { color: colors.text }]}>Ingredients</Text>
                  {selectedRec.ingredients.map((ing, idx) => (
                    <View key={idx} style={styles.ingredientRow}>
                      <Ionicons name="checkmark-circle" size={18} color="#10B981" />
                      <Text style={[styles.ingredientText, { color: colors.text }]}>{ing}</Text>
                    </View>
                  ))}
                </View>
              )}

              {/* Benefits */}
              {selectedRec?.benefits && selectedRec.benefits.length > 0 && (
                <View style={styles.modalSection}>
                  <Text style={[styles.modalSectionTitle, { color: colors.text }]}>Key Benefits</Text>
                  {selectedRec.benefits.map((benefit, idx) => (
                    <View key={idx} style={styles.benefitRow}>
                      <Ionicons name="sparkles" size={16} color="#F59E0B" />
                      <Text style={[styles.benefitText, { color: colors.textSecondary }]}>{benefit}</Text>
                    </View>
                  ))}
                </View>
              )}

              {/* Meal Type Selection Row */}
              <View style={styles.modalSection}>
                <Text style={[styles.modalSectionTitle, { color: colors.text }]}>Select Meal Category</Text>
                <View style={styles.mealTypeSelector}>
                  {(['breakfast', 'lunch', 'dinner', 'snack'] as const).map((type) => {
                    const isSelected = selectedMealType === type;
                    return (
                      <TouchableOpacity
                        key={type}
                        style={[
                          styles.mealTypePill,
                          {
                            backgroundColor: darkTheme ? '#334155' : '#F3F4F6',
                            borderColor: darkTheme ? '#475569' : '#E5E7EB'
                          },
                          isSelected && styles.mealTypePillSelected,
                        ]}
                        onPress={() => setSelectedMealType(type)}
                        activeOpacity={0.8}
                      >
                        <Ionicons 
                          name={getMealIcon(type) as any} 
                          size={14} 
                          color={isSelected ? '#FFFFFF' : colors.textSecondary} 
                          style={{ marginRight: 4 }}
                        />
                        <Text style={[
                          styles.mealTypePillText,
                          { color: colors.textSecondary },
                          isSelected && styles.mealTypePillTextSelected,
                        ]}>
                          {type.charAt(0).toUpperCase() + type.slice(1)}
                        </Text>
                      </TouchableOpacity>
                    );
                  })}
                </View>
              </View>
            </ScrollView>

            {/* Modal Action CTA */}
            <View style={[styles.modalFooter, { borderColor: colors.separator }]}>
              <TouchableOpacity
                style={styles.logButton}
                onPress={handleLogRecommendedMeal}
                disabled={loggingMeal}
                activeOpacity={0.85}
              >
                {loggingMeal ? (
                  <ActivityIndicator size="small" color="#FFFFFF" />
                ) : (
                  <>
                    <Ionicons name="add-circle-outline" size={20} color="#FFFFFF" style={{ marginRight: 8 }} />
                    <Text style={styles.logButtonText}>LOG MEAL FOR TODAY</Text>
                  </>
                )}
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>
      {/* AI Pop-up Coach Modal */}
      <AICoachModal
        visible={showAIModal}
        notification={latestNotification}
        onDismiss={handleDismissInsight}
      />
      <SubscriptionUpgradeModal
        visible={paywallVisible}
        title={paywallData?.title || "✨ Unlock AI Diet Recommendations"}
        message={paywallData?.message || "Upgrade to Advanced Premium to unlock the AI Nutrition Coach, camera meal scanners, and biomechanics posture checking!"}
        pricingOptions={paywallData?.pricing_options}
        onClose={() => setPaywallVisible(false)}
        onUpgrade={() => {
          setPaywallVisible(false);
          navigation.navigate('SubscriptionSelection', { userId, firstName });
        }}
      />

      {/* Meal Detail Modal */}
      <Modal
        visible={selectedMeal !== null}
        transparent={true}
        animationType="fade"
        onRequestClose={() => setSelectedMeal(null)}
      >
        <View style={styles.detailModalOverlay}>
          <View style={[styles.detailModalContent, { backgroundColor: colors.cardBg }]}>
            {selectedMeal && (
              <>
                {/* Modal Header */}
                <View style={styles.detailModalHeader}>
                  <View style={[styles.detailModalIconContainer, { backgroundColor: getMealColor(selectedMeal.meal_type) }]}>
                    <Ionicons name={getMealIcon(selectedMeal.meal_type) as any} size={24} color="#fff" />
                  </View>
                  <Text style={styles.detailModalSubtitle}>{selectedMeal.meal_type.toUpperCase()}</Text>
                  <TouchableOpacity onPress={() => setSelectedMeal(null)} style={[styles.detailCloseButton, { backgroundColor: darkTheme ? '#334155' : '#F3F4F6' }]}>
                    <Ionicons name="close" size={24} color={colors.textSecondary} />
                  </TouchableOpacity>
                </View>

                {/* Scrollable Modal Body */}
                <ScrollView 
                  style={styles.detailModalScrollBody}
                  showsVerticalScrollIndicator={false}
                  contentContainerStyle={{ paddingBottom: 16 }}
                >
                  <View style={styles.detailModalBody}>
                    <Text style={[styles.detailModalMealName, { color: colors.text }]}>{selectedMeal.meal_name}</Text>
                    <Text style={[styles.detailModalMealTime, { color: colors.textSecondary }]}>Logged at {selectedMeal.meal_time}</Text>

                    <View style={[styles.detailCaloriesBadge, darkTheme && { backgroundColor: '#064E3B', borderColor: '#065F46' }]}>
                      <Text style={[styles.detailCaloriesValue, darkTheme && { color: '#34D399' }]}>{selectedMeal.calories}</Text>
                      <Text style={[styles.detailCaloriesLabel, darkTheme && { color: '#A7F3D0' }]}>Total Calories (kcal)</Text>
                    </View>

                    {/* Meal Items Section */}
                    {selectedMeal.items && selectedMeal.items.length > 0 && (
                      <View style={[styles.detailItemsSection, { backgroundColor: darkTheme ? '#0F172A' : '#F9FAFB', borderColor: colors.border }]}>
                        <Text style={[styles.detailSectionTitle, { color: colors.text }]}>Meal Items</Text>
                        {selectedMeal.items.map((item, idx) => (
                          <View key={idx} style={[styles.detailItemRow, { borderBottomColor: colors.border }]}>
                            <View style={styles.detailItemRowLeft}>
                              <Ionicons name="ellipse" size={6} color="#10B981" style={styles.detailBulletIcon} />
                              <View style={{ flex: 1, paddingLeft: 4 }}>
                                <Text style={[styles.detailItemName, { color: colors.text }]}>{item.name}</Text>
                                {item.amount && <Text style={[styles.detailItemAmount, { color: colors.textSecondary }]}>{item.amount}</Text>}
                              </View>
                            </View>
                            <Text style={[styles.detailItemCalories, { color: colors.textSecondary }]}>{item.calories} kcal</Text>
                          </View>
                        ))}
                      </View>
                    )}

                    {/* Macros Breakdown Section */}
                    <Text style={[styles.detailSectionTitle, { color: colors.text }]}>Macronutrients Breakdown</Text>

                    {/* Protein */}
                    <View style={styles.detailMacroRow}>
                      <View style={styles.detailMacroInfo}>
                        <Text style={[styles.detailMacroLabel, { color: '#3B82F6' }]}>Protein</Text>
                        <Text style={[styles.detailMacroValue, { color: colors.textSecondary }]}>{selectedMeal.protein || 0}g</Text>
                      </View>
                      <View style={[styles.detailProgressBarBg, { backgroundColor: darkTheme ? '#334155' : '#E5E7EB' }]}>
                        <View style={[styles.detailProgressBarFill, { width: `${Math.min(100, Math.round((selectedMeal.protein || 0) * 1.5))}%`, backgroundColor: '#3B82F6' }]} />
                      </View>
                    </View>

                    {/* Carbs */}
                    <View style={styles.detailMacroRow}>
                      <View style={styles.detailMacroInfo}>
                        <Text style={[styles.detailMacroLabel, { color: '#F59E0B' }]}>Carbohydrates</Text>
                        <Text style={[styles.detailMacroValue, { color: colors.textSecondary }]}>{selectedMeal.carbs || 0}g</Text>
                      </View>
                      <View style={[styles.detailProgressBarBg, { backgroundColor: darkTheme ? '#334155' : '#E5E7EB' }]}>
                        <View style={[styles.detailProgressBarFill, { width: `${Math.min(100, Math.round((selectedMeal.carbs || 0) * 1.2))}%`, backgroundColor: '#F59E0B' }]} />
                      </View>
                    </View>

                    {/* Fats */}
                    <View style={styles.detailMacroRow}>
                      <View style={styles.detailMacroInfo}>
                        <Text style={[styles.detailMacroLabel, { color: '#EF4444' }]}>Fats</Text>
                        <Text style={[styles.detailMacroValue, { color: colors.textSecondary }]}>{selectedMeal.fats || 0}g</Text>
                      </View>
                      <View style={[styles.detailProgressBarBg, { backgroundColor: darkTheme ? '#334155' : '#E5E7EB' }]}>
                        <View style={[styles.detailProgressBarFill, { width: `${Math.min(100, Math.round((selectedMeal.fats || 0) * 2.5))}%`, backgroundColor: '#EF4444' }]} />
                      </View>
                    </View>
                  </View>
                </ScrollView>

                {/* Primary Action Button */}
                <TouchableOpacity onPress={() => setSelectedMeal(null)} style={styles.detailModalActionButton}>
                  <Text style={styles.detailModalActionButtonText}>Back to Today</Text>
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
    borderRadius: 24,
    marginBottom: 20,
    overflow: 'hidden',
  },
  caloriesCardOverlay: {
    backgroundColor: 'rgba(15, 23, 42, 0.75)',
    padding: 24,
  },
  caloriesHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 24,
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
  statusLabel: {
    fontSize: 12,
    fontWeight: '800',
    letterSpacing: 1,
    marginBottom: 4,
    textTransform: 'uppercase',
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
  aiLockedCard: {
    padding: 16,
    borderRadius: 24,
    borderWidth: 1.5,
    marginBottom: 20,
    gap: 16,
  },
  aiLockedContent: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 14,
  },
  aiLockedIconCircle: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: '#FFFFFF',
    justifyContent: 'center',
    alignItems: 'center',
    shadowColor: '#10B981',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.1,
    shadowRadius: 6,
    elevation: 2,
  },
  aiLockedTitle: {
    fontSize: 15,
    fontWeight: '800',
    color: '#1F2937',
  },
  aiLockedDesc: {
    fontSize: 11,
    color: '#065F46',
    lineHeight: 16,
  },
  aiLockedBtn: {
    height: 40,
    borderRadius: 12,
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
    gap: 6,
    width: '100%',
  },
  aiLockedBtnText: {
    color: '#FFFFFF',
    fontSize: 13,
    fontWeight: '700',
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
  aiSectionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginTop: 10,
    marginBottom: 16,
    paddingHorizontal: 4,
  },
  aiLoadingContainer: {
    backgroundColor: '#FFFFFF',
    borderRadius: 24,
    padding: 24,
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
    gap: 12,
    borderWidth: 1,
    borderColor: '#F3F4F6',
  },
  aiLoadingText: {
    fontSize: 14,
    color: '#6B7280',
    fontWeight: '600',
  },
  aiScrollContainer: {
    gap: 16,
    paddingRight: 20,
    paddingBottom: 8,
  },
  aiCard: {
    backgroundColor: '#FFFFFF',
    borderRadius: 24,
    padding: 20,
    width: 280,
    borderWidth: 1.5,
    borderColor: '#D1FAE5',
    shadowColor: '#10B981',
    shadowOffset: { width: 0, height: 8 },
    shadowOpacity: 0.1,
    shadowRadius: 12,
    elevation: 4,
  },
  aiCardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 12,
    gap: 12,
  },
  aiCardTitle: {
    flex: 1,
    fontSize: 18,
    fontWeight: '800',
    color: '#1F2937',
  },
  aiCaloriesBadge: {
    backgroundColor: '#F0FDF4',
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 12,
  },
  aiCaloriesText: {
    fontSize: 12,
    fontWeight: '800',
    color: '#10B981',
  },
  aiReasonText: {
    fontSize: 13,
    color: '#4B5563',
    lineHeight: 20,
    marginBottom: 16,
    fontStyle: 'italic',
  },
  aiMacrosRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    backgroundColor: '#F9FAFB',
    borderRadius: 16,
    padding: 12,
  },
  aiMacroItem: {
    alignItems: 'center',
  },
  aiMacroLabel: {
    fontSize: 11,
    fontWeight: '800',
    textTransform: 'uppercase',
    letterSpacing: 0.5,
    marginBottom: 4,
  },
  aiMacroValue: {
    fontSize: 14,
    fontWeight: '700',
    color: '#1F2937',
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(15, 23, 42, 0.6)',
    justifyContent: 'flex-end',
  },
  modalContent: {
    backgroundColor: '#FFFFFF',
    borderTopLeftRadius: 32,
    borderTopRightRadius: 32,
    maxHeight: '85%',
    paddingBottom: 34,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: -10 },
    shadowOpacity: 0.15,
    shadowRadius: 20,
    elevation: 20,
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    paddingHorizontal: 24,
    paddingTop: 24,
    paddingBottom: 16,
    borderBottomWidth: 1,
    borderColor: '#F3F4F6',
  },
  modalTitleContainer: {
    flex: 1,
    marginRight: 16,
  },
  modalTitle: {
    fontSize: 22,
    fontWeight: '800',
    color: '#1F2937',
    marginBottom: 6,
  },
  prepTimeBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#ECFDF5',
    alignSelf: 'flex-start',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 8,
    gap: 4,
  },
  prepTimeText: {
    fontSize: 12,
    fontWeight: '700',
    color: '#10B981',
  },
  closeButton: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: '#F3F4F6',
    justifyContent: 'center',
    alignItems: 'center',
  },
  modalScrollView: {
    paddingHorizontal: 24,
  },
  modalScrollContent: {
    paddingTop: 20,
    paddingBottom: 24,
  },
  modalMacrosGrid: {
    flexDirection: 'row',
    gap: 8,
    marginBottom: 24,
  },
  modalMacroCard: {
    flex: 1,
    alignItems: 'center',
    paddingVertical: 12,
    borderRadius: 16,
    borderWidth: 1,
  },
  modalMacroLabel: {
    fontSize: 11,
    fontWeight: '800',
    textTransform: 'uppercase',
    letterSpacing: 0.5,
    marginBottom: 4,
  },
  modalMacroValue: {
    fontSize: 15,
    fontWeight: '800',
  },
  modalSection: {
    marginBottom: 24,
  },
  modalSectionTitle: {
    fontSize: 16,
    fontWeight: '800',
    color: '#374151',
    letterSpacing: 0.5,
    marginBottom: 12,
    textTransform: 'uppercase',
  },
  modalDescriptionText: {
    fontSize: 15,
    color: '#4B5563',
    lineHeight: 22,
  },
  ingredientRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    marginBottom: 10,
  },
  ingredientText: {
    fontSize: 15,
    color: '#1F2937',
    fontWeight: '500',
  },
  benefitRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 10,
    marginBottom: 10,
  },
  benefitText: {
    flex: 1,
    fontSize: 15,
    color: '#4B5563',
    lineHeight: 20,
  },
  mealTypeSelector: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  mealTypePill: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#F3F4F6',
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#E5E7EB',
  },
  mealTypePillSelected: {
    backgroundColor: '#10B981',
    borderColor: '#10B981',
  },
  mealTypePillText: {
    fontSize: 13,
    fontWeight: '700',
    color: '#4B5563',
  },
  mealTypePillTextSelected: {
    color: '#FFFFFF',
  },
  modalFooter: {
    paddingHorizontal: 24,
    paddingTop: 16,
    borderTopWidth: 1,
    borderColor: '#F3F4F6',
  },
  logButton: {
    backgroundColor: '#10B981',
    borderRadius: 18,
    paddingVertical: 16,
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
    shadowColor: '#10B981',
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.2,
    shadowRadius: 10,
    elevation: 5,
  },
  logButtonText: {
    fontSize: 16,
    fontWeight: '800',
    color: '#FFFFFF',
    letterSpacing: 0.5,
  },
  insightBanner: {
    borderRadius: 20,
    borderWidth: 1,
    padding: 16,
    marginBottom: 20,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.05,
    shadowRadius: 10,
    elevation: 3,
  },
  insightBannerWarning: {
    backgroundColor: '#FFFBEB',
    borderColor: '#FDE68A',
  },
  insightBannerTip: {
    backgroundColor: '#ECFDF5',
    borderColor: '#A7F3D0',
  },
  insightBannerContent: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 12,
  },
  insightIconWrapper: {
    width: 36,
    height: 36,
    borderRadius: 12,
    justifyContent: 'center',
    alignItems: 'center',
  },
  insightIconWrapperWarning: {
    backgroundColor: '#FEF3C7',
  },
  insightIconWrapperTip: {
    backgroundColor: '#D1FAE5',
  },
  insightTextWrapper: {
    flex: 1,
    paddingRight: 8,
  },
  insightTitle: {
    fontSize: 11,
    fontWeight: '800',
    letterSpacing: 1.2,
    marginBottom: 4,
    textTransform: 'uppercase',
  },
  insightTitleWarning: {
    color: '#D97706',
  },
  insightTitleTip: {
    color: '#059669',
  },
  insightText: {
    fontSize: 13,
    color: '#374151',
    lineHeight: 18,
    fontWeight: '500',
  },
  insightDismissButton: {
    width: 24,
    height: 24,
    borderRadius: 12,
    backgroundColor: 'rgba(0,0,0,0.05)',
    justifyContent: 'center',
    alignItems: 'center',
    alignSelf: 'center',
  },
  detailModalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(15, 23, 42, 0.4)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 24,
  },
  detailModalContent: {
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
  detailModalHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 20,
  },
  detailModalIconContainer: {
    width: 48,
    height: 48,
    borderRadius: 16,
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 12,
  },
  detailModalSubtitle: {
    fontSize: 12,
    fontWeight: '800',
    color: '#9CA3AF',
    letterSpacing: 1.5,
    flex: 1,
  },
  detailCloseButton: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: '#F3F4F6',
    justifyContent: 'center',
    alignItems: 'center',
  },
  detailModalBody: {
    marginBottom: 24,
  },
  detailModalMealName: {
    fontSize: 22,
    fontWeight: '900',
    color: '#111827',
    marginBottom: 4,
  },
  detailModalMealTime: {
    fontSize: 14,
    color: '#9CA3AF',
    fontWeight: '600',
    marginBottom: 20,
  },
  detailCaloriesBadge: {
    backgroundColor: '#ECFDF5',
    borderRadius: 20,
    paddingVertical: 16,
    paddingHorizontal: 20,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: '#A7F3D0',
    marginBottom: 24,
  },
  detailCaloriesValue: {
    fontSize: 32,
    fontWeight: '900',
    color: '#10B981',
  },
  detailCaloriesLabel: {
    fontSize: 12,
    fontWeight: '700',
    color: '#047857',
    marginTop: 4,
    textTransform: 'uppercase',
  },
  detailSectionTitle: {
    fontSize: 15,
    fontWeight: '800',
    color: '#111827',
    marginBottom: 16,
    letterSpacing: 0.3,
  },
  detailMacroRow: {
    marginBottom: 16,
  },
  detailMacroInfo: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 6,
  },
  detailMacroLabel: {
    fontSize: 14,
    fontWeight: '800',
  },
  detailMacroValue: {
    fontSize: 14,
    fontWeight: '700',
    color: '#4B5563',
  },
  detailProgressBarBg: {
    height: 8,
    width: '100%',
    backgroundColor: '#E5E7EB',
    borderRadius: 4,
    overflow: 'hidden',
  },
  detailProgressBarFill: {
    height: '100%',
    borderRadius: 4,
  },
  detailModalActionButton: {
    backgroundColor: '#10B981',
    borderRadius: 16,
    paddingVertical: 14,
    alignItems: 'center',
    justifyContent: 'center',
  },
  detailModalActionButtonText: {
    fontSize: 16,
    fontWeight: '800',
    color: '#FFFFFF',
  },
  detailModalScrollBody: {
    flexGrow: 0,
    marginBottom: 16,
  },
  detailItemsSection: {
    marginBottom: 24,
    backgroundColor: '#F9FAFB',
    borderRadius: 20,
    padding: 16,
    borderWidth: 1,
    borderColor: '#F3F4F6',
  },
  detailItemRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 10,
    borderBottomWidth: 1,
    borderBottomColor: '#F3F4F6',
  },
  detailItemRowLeft: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
  },
  detailBulletIcon: {
    marginRight: 6,
  },
  detailItemName: {
    fontSize: 14,
    fontWeight: '700',
    color: '#1F2937',
  },
  detailItemAmount: {
    fontSize: 12,
    fontWeight: '500',
    color: '#9CA3AF',
    marginTop: 2,
  },
  detailItemCalories: {
    fontSize: 14,
    fontWeight: '700',
    color: '#4B5563',
  },
});
