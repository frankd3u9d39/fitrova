import { SafeAreaView } from 'react-native-safe-area-context';
import { CustomAlert } from '../../../components/common/CustomAlert';
import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  TextInput,
  KeyboardAvoidingView,
  Platform,
  Dimensions,
  ActivityIndicator
} from 'react-native';
import { useNavigation, useRoute, RouteProp } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { RootStackParamList } from '../../../navigation/types';
import { ProgressHeader } from '../../../components/common/ProgressHeader';
import { Button } from '../../../components/buttons/Button';
import { Ionicons } from '@expo/vector-icons';
import { theme } from '../../../theme';
import { endpoints } from '../../../services/api/apiClient';

type NavigationProp = NativeStackNavigationProp<RootStackParamList, 'GoalSetting'>;
type GoalSettingRouteProp = RouteProp<RootStackParamList, 'GoalSetting'>;

interface GoalOption {
  id: string;
  title: string;
  description: string;
  icon: keyof typeof Ionicons.glyphMap;
}

export const GoalSettingScreen = () => {
  const navigation = useNavigation<NavigationProp>();
  const route = useRoute<GoalSettingRouteProp>();
  const params = route.params;

  const [selectedGoal, setSelectedGoal] = useState<string>('');
  const [targetWeight, setTargetWeight] = useState('75.0');
  const [targetDate, setTargetDate] = useState('');
  
  // AI States
  const [aiRecommendation, setAiRecommendation] = useState('Select a goal to see your personalized AI recommendation.');
  const [isAiLoading, setIsAiLoading] = useState(false);

  const goals: GoalOption[] = [
    {
      id: 'Lose Weight',
      title: 'Lose Weight',
      description: 'Focus on calorie deficit and fat loss.',
      icon: 'scale-outline',
    },
    {
      id: 'Gain Muscle',
      title: 'Gain Muscle',
      description: 'Build strength and increase mass.',
      icon: 'barbell-outline',
    },
    {
      id: 'Endurance',
      title: 'Endurance',
      description: 'Run longer, swim further, go harder.',
      icon: 'bicycle-outline',
    },
    {
      id: 'General Health',
      title: 'General Health',
      description: 'Maintain wellness and daily energy.',
      icon: 'heart-outline',
    },
  ];

  // Fetch AI Recommendation when goal changes
  useEffect(() => {
    if (selectedGoal) {
      fetchAIRecommendation();
    }
  }, [selectedGoal]);

  const fetchAIRecommendation = async () => {
    setIsAiLoading(true);
    try {
      const response = await fetch(endpoints.getAIRecommendation, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          age: params.age,
          gender: params.gender,
          height: params.height,
          weight: params.weight,
          activityLevel: params.activityLevel,
          goal: selectedGoal
        }),
      });

      const result = await response.json();
      if (result.status === 'success') {
        setAiRecommendation(result.data.recommendation);
        if (result.data.suggested_target_weight) {
          setTargetWeight(result.data.suggested_target_weight.toString());
        }
        
        // Calculate a target date based on suggested weeks
        if (result.data.suggested_weeks) {
          const date = new Date();
          date.setDate(date.getDate() + (result.data.suggested_weeks * 7));
          const formattedDate = `${(date.getMonth() + 1).toString().padStart(2, '0')}/${date.getDate().toString().padStart(2, '0')}/${date.getFullYear()}`;
          setTargetDate(formattedDate);
        }
      } else {
        setAiRecommendation(result.message || 'AI coach is busy. Please try again.');
      }
    } catch (error) {
      console.error('AI Error:', error);
      setAiRecommendation('AI coach is temporarily offline. You can still set your targets manually!');
    } finally {
      setIsAiLoading(false);
    }
  };

  const handleNext = async () => {
    if (!selectedGoal) {
      CustomAlert.alert('Selection Required', 'Please select your primary fitness goal.');
      return;
    }

    try {
      const response = await fetch(endpoints.saveProfile, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          userId: params.userId,
          selectedGoal,
          targetWeight,
          targetDate,
          survey_step: 'Restrictions',
          firstName: params.firstName,
          hasEquipment: params.hasEquipment
        }),
      });

      const data = await response.json();

      if (response.ok && data.status === 'success') {
        navigation.navigate('Restrictions', {
          ...params,
          selectedGoal,
          targetWeight,
          targetDate,
          firstName: params.firstName,
          hasEquipment: params.hasEquipment
        });
      } else {
        CustomAlert.alert('Error', data.message || 'Failed to save progress');
      }
    } catch (error) {
      CustomAlert.alert('Error', 'Network request failed');
    }
  };

  return (
    <SafeAreaView style={styles.container}>
      <KeyboardAvoidingView 
        style={styles.keyboardView}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      >
        <ScrollView
          style={styles.scrollView}
          contentContainerStyle={styles.scrollContent}
          showsVerticalScrollIndicator={false}
          keyboardShouldPersistTaps="handled"
        >
          {/* Progress Header */}
          <ProgressHeader
            currentStep={2}
            totalSteps={3}
            stepLabel="Goal Setting"
            onBackPress={() => navigation.goBack()}
          />

          {/* Title */}
          <Text style={styles.title}>What is your primary goal?</Text>
          <Text style={styles.subtitle}>This helps us customize your path.</Text>

          {/* Goal Options */}
          <View style={styles.goalsGrid}>
            {goals.map((goal) => (
              <TouchableOpacity
                key={goal.id}
                style={[
                  styles.goalCard,
                  selectedGoal === goal.id && styles.goalCardSelected,
                ]}
                onPress={() => setSelectedGoal(goal.id)}
              >
                {selectedGoal === goal.id && (
                  <View style={styles.checkmark}>
                    <Ionicons name="checkmark" size={14} color="#FFFFFF" />
                  </View>
                )}
                <View style={[styles.goalIconContainer, selectedGoal === goal.id && styles.goalIconContainerSelected]}>
                  <Ionicons name={goal.icon} size={28} color={selectedGoal === goal.id ? theme.colors.primary : theme.colors.text} />
                </View>
                <Text style={styles.goalTitle}>{goal.title}</Text>
                <Text style={styles.goalDescription}>{goal.description}</Text>
              </TouchableOpacity>
            ))}
          </View>

          {/* Target Goal Section */}
          <View style={styles.sectionHeader}>
            <Text style={styles.sectionTitle}>Set your target</Text>
            <Text style={styles.sectionSubtitle}>Define where you want to be.</Text>
          </View>

          <View style={styles.targetMainCard}>
            {/* Target Weight */}
            <View style={styles.targetRow}>
              <View style={styles.targetIconCircle}>
                <Ionicons name="scale-outline" size={20} color={theme.colors.primary} />
              </View>
              <View style={styles.targetContent}>
                <Text style={styles.targetLabel}>TARGET WEIGHT</Text>
                <View style={styles.targetInputContainer}>
                  <TextInput
                    style={styles.targetInput}
                    value={targetWeight}
                    onChangeText={setTargetWeight}
                    keyboardType="decimal-pad"
                    placeholder="75.0"
                    placeholderTextColor="#9CA3AF"
                  />
                  <Text style={styles.targetUnit}>kg</Text>
                </View>
              </View>
            </View>

            <View style={styles.targetDivider} />

            {/* Target Date */}
            <View style={styles.targetRow}>
              <View style={styles.targetIconCircle}>
                <Ionicons name="calendar-outline" size={20} color={theme.colors.primary} />
              </View>
              <View style={styles.targetContent}>
                <Text style={styles.targetLabel}>TARGET DATE</Text>
                <View style={styles.targetInputContainer}>
                  <TextInput
                    style={styles.targetInput}
                    value={targetDate}
                    onChangeText={setTargetDate}
                    placeholder="mm/dd/yyyy"
                    placeholderTextColor="#9CA3AF"
                  />
                  <Ionicons name="chevron-forward" size={16} color="#9CA3AF" />
                </View>
              </View>
            </View>
          </View>

          {/* AI Recommendation */}
          <View style={[styles.recommendationCard, isAiLoading && styles.recommendationCardLoading]}>
            <View style={styles.recommendationHeader}>
              <View style={styles.aiIconContainer}>
                <Ionicons name="sparkles" size={18} color="#FFFFFF" />
              </View>
              <Text style={styles.recommendationTitle}>Gemini 2.5 Flash</Text>
              {isAiLoading && <ActivityIndicator size="small" color={theme.colors.primary} style={{ marginLeft: 'auto' }} />}
            </View>
            
            {isAiLoading ? (
              <View style={styles.loadingContainer}>
                <Text style={styles.loadingText}>Analyzing your profile...</Text>
              </View>
            ) : (
              <Text style={styles.recommendationText}>
                {aiRecommendation}
              </Text>
            )}
            
            {!isAiLoading && selectedGoal && (
              <View style={styles.badgeContainer}>
                <Text style={styles.badgeText}>Personalized Strategy</Text>
              </View>
            )}
          </View>

          {/* Next Button */}
          <Button 
            title="Continue to Last Step" 
            onPress={handleNext}
            style={styles.nextButton}
            disabled={isAiLoading}
          />

          <View style={styles.bottomSpacer} />
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  keyboardView: {
    flex: 1,
  },
  scrollView: {
    flex: 1,
  },
  scrollContent: {
    paddingHorizontal: theme.spacing.lg,
    paddingTop: 50,
  },
  title: {
    ...theme.typography.h2,
    marginBottom: 8,
  },
  subtitle: {
    ...theme.typography.body,
    color: theme.colors.textSecondary,
    marginBottom: 24,
  },
  goalsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
    marginBottom: 32,
  },
  goalCard: {
    width: (Dimensions.get('window').width - 40 - 12) / 2,
    backgroundColor: theme.colors.surface,
    borderRadius: 20,
    padding: 16,
    borderWidth: 1.5,
    borderColor: 'transparent',
    position: 'relative',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 10,
    elevation: 2,
  },
  goalCardSelected: {
    borderColor: theme.colors.primary,
    backgroundColor: theme.colors.background,
  },
  checkmark: {
    position: 'absolute',
    top: 12,
    right: 12,
    width: 20,
    height: 20,
    borderRadius: 10,
    backgroundColor: theme.colors.primary,
    justifyContent: 'center',
    alignItems: 'center',
    zIndex: 1,
  },
  goalIconContainer: {
    width: 48,
    height: 48,
    borderRadius: 16,
    backgroundColor: theme.colors.background,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 12,
  },
  goalIconContainerSelected: {
    backgroundColor: theme.colors.surface,
  },
  goalTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: theme.colors.text,
    marginBottom: 4,
  },
  goalDescription: {
    fontSize: 12,
    color: theme.colors.textSecondary,
    lineHeight: 16,
  },
  sectionHeader: {
    marginBottom: 16,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: theme.colors.text,
  },
  sectionSubtitle: {
    fontSize: 14,
    color: theme.colors.textSecondary,
  },
  targetMainCard: {
    backgroundColor: theme.colors.surface,
    borderRadius: 24,
    padding: 20,
    marginBottom: 24,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  targetRow: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  targetIconCircle: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: theme.colors.background,
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 16,
  },
  targetContent: {
    flex: 1,
  },
  targetLabel: {
    fontSize: 11,
    fontWeight: '800',
    color: theme.colors.textSecondary,
    letterSpacing: 1,
    marginBottom: 4,
  },
  targetInputContainer: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  targetInput: {
    flex: 1,
    fontSize: 20,
    fontWeight: '600',
    color: theme.colors.text,
    padding: 0,
  },
  targetUnit: {
    fontSize: 16,
    fontWeight: '700',
    color: theme.colors.textSecondary,
    marginLeft: 8,
  },
  targetDivider: {
    height: 1,
    backgroundColor: theme.colors.border,
    marginVertical: 16,
    marginLeft: 60,
  },
  recommendationCard: {
    backgroundColor: '#F0FDF4',
    borderRadius: 24,
    padding: 20,
    marginBottom: 32,
    borderWidth: 1,
    borderColor: '#D1FAE5',
    shadowColor: '#10B981',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.1,
    shadowRadius: 12,
    elevation: 4,
  },
  recommendationCardLoading: {
    opacity: 0.8,
  },
  recommendationHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 12,
  },
  aiIconContainer: {
    width: 32,
    height: 32,
    borderRadius: 12,
    backgroundColor: theme.colors.primary,
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 12,
  },
  recommendationTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: '#065F46',
  },
  recommendationText: {
    fontSize: 14,
    color: '#047857',
    lineHeight: 22,
    fontWeight: '500',
  },
  loadingContainer: {
    paddingVertical: 10,
  },
  loadingText: {
    fontSize: 14,
    color: '#059669',
    fontStyle: 'italic',
  },
  badgeContainer: {
    alignSelf: 'flex-start',
    backgroundColor: '#D1FAE5',
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 8,
    marginTop: 16,
  },
  badgeText: {
    fontSize: 10,
    fontWeight: '700',
    color: '#059669',
    textTransform: 'uppercase',
  },
  nextButton: {
    marginBottom: 20,
  },
  bottomSpacer: {
    height: 60,
  },
});
export default GoalSettingScreen;
