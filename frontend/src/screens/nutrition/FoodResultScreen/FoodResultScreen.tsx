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
import { RootStackParamList } from '../../../navigation/types';
import { SubscriptionUpgradeModal } from '../../../components/common/SubscriptionUpgradeModal';

const { width } = Dimensions.get('window');

type FoodResultRouteProp = RouteProp<RootStackParamList, 'FoodResult'>;

export const FoodResultScreen = () => {
  const navigation = useNavigation<any>();
  const route = useRoute<FoodResultRouteProp>();
  const { imageUri, base64, userId } = route.params;
  
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [result, setResult] = useState<any>(null);
  const [paywallVisible, setPaywallVisible] = useState(false);
  const [paywallData, setPaywallData] = useState<any>(null);

  useEffect(() => {
    // Call the real scan API
    handleScan();
  }, []);

  const handleScan = async () => {
    try {
      setLoading(true);
      // Call the real scan API with userId to enable personalized coaching
      const data = await nutritionService.scanMeal(base64, userId);
      setResult(data);
      setLoading(false);
    } catch (error: any) {
      setLoading(false);
      
      if (error && (error.status === 'subscription_locked' || error.statusCode === 403)) {
        console.log('Scan trial exhausted (paywall locked):', error.message || 'Trial used.');
        setPaywallData(error);
        setPaywallVisible(true);
      } else {
        console.error('Scan error:', error);
        Alert.alert("Error", "Could not identify meal. Please try again.");
        navigation.goBack();
      }
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
      
      // Save the meal and propagate potential pattern warnings/coaching insights
      const hasPatternAlert = result.health_analysis?.pattern_alert;
      const coachFeedback = result.health_analysis?.coach_feedback;
      
      await nutritionService.logMeal({
        user_id: userId,
        meal_name: result.meal_name,
        calories: result.calories,
        protein: result.protein,
        carbs: result.carbs,
        fats: result.fats,
        meal_type: getMealType(),
        insight_text: hasPatternAlert || coachFeedback || null,
        insight_type: hasPatternAlert ? 'warning' : 'tip'
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

  if (!result) {
    return (
      <SafeAreaView style={styles.container}>
        <SubscriptionUpgradeModal
          visible={paywallVisible}
          title={paywallData?.title || "✨ Unlock AI Meal Scanner"}
          message={paywallData?.message || "Trial used. Upgrade to Premium or Advanced Premium to unlock unlimited access!"}
          pricingOptions={paywallData?.pricing_options}
          onClose={() => {
            setPaywallVisible(false);
            navigation.goBack();
          }}
          onUpgrade={() => {
            setPaywallVisible(false);
            navigation.navigate('SubscriptionSelection', { userId });
          }}
        />
      </SafeAreaView>
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

          {/* AI Coaching Analysis Block */}
          {result.health_analysis && (
            <Animatable.View animation="fadeInUp" delay={200} style={styles.aiCoachingCard}>
              <View style={styles.aiCoachingHeader}>
                <View style={styles.aiCoachingTitleRow}>
                  <Ionicons name="sparkles" size={18} color={theme.colors.primary} />
                  <Text style={styles.aiCoachingTitle}>AI NUTRITION COACH</Text>
                </View>
                <View style={[
                  styles.healthRatingBadge, 
                  result.health_analysis.health_rating === 'Excellent' && { backgroundColor: '#D1FAE5' },
                  result.health_analysis.health_rating === 'Good' && { backgroundColor: '#E0F2FE' },
                  result.health_analysis.health_rating === 'Caution' && { backgroundColor: '#FEF3C7' },
                  result.health_analysis.health_rating === 'Avoid' && { backgroundColor: '#FEE2E2' },
                ]}>
                  <Text style={[
                    styles.healthRatingText,
                    result.health_analysis.health_rating === 'Excellent' && { color: '#065F46' },
                    result.health_analysis.health_rating === 'Good' && { color: '#075985' },
                    result.health_analysis.health_rating === 'Caution' && { color: '#92400E' },
                    result.health_analysis.health_rating === 'Avoid' && { color: '#991B1B' },
                  ]}>
                    {result.health_analysis.health_rating?.toUpperCase()}
                  </Text>
                </View>
              </View>

              <Text style={styles.evaluationText}>{result.health_analysis.evaluation}</Text>

              {result.health_analysis.dietary_imbalances && result.health_analysis.dietary_imbalances.length > 0 && (
                <View style={styles.imbalancesContainer}>
                  {result.health_analysis.dietary_imbalances.map((imb: any, idx: number) => {
                    if (!imb) return null;
                    const textVal = typeof imb === 'object' ? (imb.name || imb.description || JSON.stringify(imb)) : String(imb);
                    if (!textVal.trim()) return null;
                    return (
                      <View key={idx} style={styles.imbalanceChip}>
                        <Ionicons name="alert-circle-outline" size={12} color="#DC2626" />
                        <Text style={styles.imbalanceChipText}>{textVal.trim()}</Text>
                      </View>
                    );
                  })}
                </View>
              )}

              {/* Pattern Alert Callout */}
              {result.health_analysis.pattern_alert && (
                <Animatable.View animation="shake" duration={800} style={styles.patternAlertCard}>
                  <View style={styles.patternAlertHeader}>
                    <Ionicons name="alert-circle" size={18} color="#D97706" />
                    <Text style={styles.patternAlertTitle}>Eating Pattern Warning</Text>
                  </View>
                  <Text style={styles.patternAlertText}>{result.health_analysis.pattern_alert}</Text>
                </Animatable.View>
              )}

              {/* Alternatives Suggestions */}
              {result.health_analysis.healthier_alternatives && result.health_analysis.healthier_alternatives.length > 0 && (
                <View style={styles.alternativesSection}>
                  <Text style={styles.alternativesTitle}>HEALTHIER MEAL ALTERNATIVES</Text>
                  {result.health_analysis.healthier_alternatives.map((alt: any, idx: number) => {
                    if (!alt) return null;
                    const name = typeof alt === 'object' ? (alt.name || '') : String(alt);
                    const reason = typeof alt === 'object' ? (alt.reason || '') : '';
                    if (!name.trim()) return null;
                    return (
                      <View key={idx} style={styles.alternativeCard}>
                        <View style={styles.alternativeIconCircle}>
                          <Ionicons name="arrow-forward-circle" size={20} color="#10B981" />
                        </View>
                        <View style={{ flex: 1 }}>
                          <Text style={styles.alternativeName}>{name.trim()}</Text>
                          {reason.trim() ? <Text style={styles.alternativeReason}>{reason.trim()}</Text> : null}
                        </View>
                      </View>
                    );
                  })}
                </View>
              )}

              {/* Coach Feedback Advice */}
              {result.health_analysis.coach_feedback && (
                <View style={styles.coachFeedbackContainer}>
                  <Text style={styles.coachFeedbackTitle}>COACH FEEDBACK & ADVICE</Text>
                  <Text style={styles.coachFeedbackText}>"{result.health_analysis.coach_feedback}"</Text>
                </View>
              )}
            </Animatable.View>
          )}

          <View style={styles.divider} />

          <Text style={styles.sectionTitle}>Identified Ingredients</Text>
          {result.items && Array.isArray(result.items) && result.items.map((item: any, index: number) => {
            if (!item) return null;
            const name = typeof item === 'object' ? (item.name || '') : String(item);
            const amount = typeof item === 'object' ? (item.amount || '') : '';
            const calories = typeof item === 'object' ? (item.calories !== undefined ? String(item.calories) : '') : '';
            return (
              <View key={index} style={styles.ingredientItem}>
                <View>
                  {name.trim() ? <Text style={styles.ingredientName}>{name.trim()}</Text> : null}
                  {amount.trim() ? <Text style={styles.ingredientAmount}>{amount.trim()}</Text> : null}
                </View>
                {calories.trim() ? <Text style={styles.ingredientCalories}>{calories.trim()} kcal</Text> : null}
              </View>
            );
          })}

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
  aiCoachingCard: {
    backgroundColor: '#F9FAFB',
    borderRadius: 24,
    padding: 20,
    marginBottom: 24,
    borderWidth: 1,
    borderColor: '#E5E7EB',
  },
  aiCoachingHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 14,
  },
  aiCoachingTitleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  aiCoachingTitle: {
    fontSize: 12,
    fontWeight: '800',
    color: '#374151',
    letterSpacing: 1,
  },
  healthRatingBadge: {
    paddingHorizontal: 12,
    paddingVertical: 4,
    borderRadius: 8,
  },
  healthRatingText: {
    fontSize: 11,
    fontWeight: '800',
  },
  evaluationText: {
    fontSize: 15,
    color: '#4B5563',
    lineHeight: 22,
    marginBottom: 16,
  },
  imbalancesContainer: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
    marginBottom: 16,
  },
  imbalanceChip: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#FEF2F2',
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 10,
    gap: 4,
  },
  imbalanceChipText: {
    fontSize: 12,
    color: '#991B1B',
    fontWeight: '600',
  },
  patternAlertCard: {
    backgroundColor: '#FFFBEB',
    borderColor: '#FDE68A',
    borderWidth: 1,
    borderRadius: 16,
    padding: 16,
    marginBottom: 20,
  },
  patternAlertHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginBottom: 6,
  },
  patternAlertTitle: {
    fontSize: 14,
    fontWeight: '700',
    color: '#D97706',
  },
  patternAlertText: {
    fontSize: 13,
    color: '#92400E',
    lineHeight: 18,
  },
  alternativesSection: {
    marginBottom: 20,
  },
  alternativesTitle: {
    fontSize: 11,
    fontWeight: '800',
    color: '#9CA3AF',
    letterSpacing: 1,
    marginBottom: 12,
  },
  alternativeCard: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#FFFFFF',
    borderWidth: 1,
    borderColor: '#E5E7EB',
    borderRadius: 16,
    padding: 12,
    marginBottom: 8,
    gap: 12,
  },
  alternativeIconCircle: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: '#ECFDF5',
    justifyContent: 'center',
    alignItems: 'center',
  },
  alternativeName: {
    fontSize: 14,
    fontWeight: '700',
    color: '#111827',
    marginBottom: 2,
  },
  alternativeReason: {
    fontSize: 12,
    color: '#6B7280',
  },
  coachFeedbackContainer: {
    borderTopWidth: 1,
    borderTopColor: '#E5E7EB',
    paddingTop: 16,
  },
  coachFeedbackTitle: {
    fontSize: 11,
    fontWeight: '800',
    color: '#9CA3AF',
    letterSpacing: 1,
    marginBottom: 8,
  },
  coachFeedbackText: {
    fontSize: 14,
    fontStyle: 'italic',
    color: '#4B5563',
    lineHeight: 20,
  },
});
