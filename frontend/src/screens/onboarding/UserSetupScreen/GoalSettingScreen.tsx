import React, { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  SafeAreaView,
  ScrollView,
  TouchableOpacity,
  TextInput,
  KeyboardAvoidingView,
  Platform,
  Alert,
} from 'react-native';
import { useNavigation, useRoute, RouteProp } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { RootStackParamList } from '../../../navigation/AppNavigator';
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

  const goals: GoalOption[] = [
    {
      id: 'lose-weight',
      title: 'Lose Weight',
      description: 'Focus on calorie deficit and fat loss.',
      icon: 'scale-outline',
    },
    {
      id: 'gain-muscle',
      title: 'Gain Muscle',
      description: 'Build strength and increase mass.',
      icon: 'barbell-outline',
    },
    {
      id: 'endurance',
      title: 'Endurance',
      description: 'Run longer, swim further, go harder.',
      icon: 'bicycle-outline',
    },
    {
      id: 'general-health',
      title: 'General Health',
      description: 'Maintain wellness and daily energy.',
      icon: 'heart-outline',
    },
  ];

  const handleNext = async () => {
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
          firstName: params.firstName
        }),
      });

      const data = await response.json();

      if (response.ok && data.status === 'success') {
        navigation.navigate('Restrictions', {
          ...params,
          selectedGoal,
          targetWeight,
          targetDate,
          firstName: params.firstName
        });
      } else {
        Alert.alert('Error', data.message || 'Failed to save progress');
      }
    } catch (error) {
      Alert.alert('Error', 'Network request failed');
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
                  <Ionicons name="checkmark" size={16} color="#FFFFFF" />
                </View>
              )}
              <View style={styles.goalIconContainer}>
                <Ionicons name={goal.icon} size={32} color={theme.colors.text} />
              </View>
              <Text style={styles.goalTitle}>{goal.title}</Text>
              <Text style={styles.goalDescription}>{goal.description}</Text>
            </TouchableOpacity>
          ))}
        </View>

        {/* Target Weight */}
        <View style={styles.inputSection}>
          <Text style={styles.inputLabel}>Target Weight (kg)</Text>
          <View style={styles.inputContainer}>
            <TextInput
              style={styles.input}
              value={targetWeight}
              onChangeText={setTargetWeight}
              keyboardType="decimal-pad"
              placeholder="75.0"
              placeholderTextColor="#9CA3AF"
            />
            <Text style={styles.inputUnit}>kg</Text>
          </View>
        </View>

        {/* Target Date */}
        <View style={styles.inputSection}>
          <Text style={styles.inputLabel}>Target Date</Text>
          <View style={styles.inputContainer}>
            <TextInput
              style={styles.input}
              value={targetDate}
              onChangeText={setTargetDate}
              placeholder="mm/dd/yyyy"
              placeholderTextColor="#9CA3AF"
            />
            <Ionicons name="calendar-outline" size={20} color="#9CA3AF" />
          </View>
        </View>

        {/* AI Recommendation */}
        <View style={styles.recommendationCard}>
          <View style={styles.recommendationHeader}>
            <View style={styles.aiIconContainer}>
              <Ionicons name="sparkles" size={20} color="#FFFFFF" />
            </View>
            <Text style={styles.recommendationTitle}>AI Recommendation</Text>
          </View>
          <Text style={styles.recommendationText}>
            Based on your data, we recommend a 0.5kg/week loss. This allows you to maintain
            muscle mass while burning body fat effectively.
          </Text>
        </View>

        {/* Next Button */}
        <Button 
          title="Next" 
          onPress={handleNext}
          // icon={<Ionicons name="arrow-forward" size={20} color="#FFFFFF" />}
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
    paddingHorizontal: 20,
    paddingTop: 50,
  },
  title: {
    fontSize: 28,
    fontWeight: 'bold',
    color: theme.colors.text,
    marginBottom: 8,
  },
  subtitle: {
    fontSize: 16,
    color: '#6B7280',
    marginBottom: 24,
  },
  goalsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
    marginBottom: 32,
  },
  goalCard: {
    width: '48%',
    backgroundColor: '#FFFFFF',
    borderRadius: 16,
    padding: 20,
    borderWidth: 2,
    borderColor: '#E5E7EB',
    position: 'relative',
  },
  goalCardSelected: {
    borderColor: '#10B981',
    backgroundColor: '#F0FDF4',
  },
  checkmark: {
    position: 'absolute',
    top: 12,
    right: 12,
    width: 24,
    height: 24,
    borderRadius: 12,
    backgroundColor: '#10B981',
    justifyContent: 'center',
    alignItems: 'center',
  },
  goalIconContainer: {
    width: 56,
    height: 56,
    borderRadius: 28,
    backgroundColor: '#F3F4F6',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 12,
  },
  goalTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: theme.colors.text,
    marginBottom: 4,
  },
  goalDescription: {
    fontSize: 13,
    color: '#6B7280',
    lineHeight: 18,
  },
  inputSection: {
    marginBottom: 20,
  },
  inputLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: theme.colors.text,
    marginBottom: 8,
  },
  inputContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#F3F4F6',
    borderRadius: 12,
    paddingHorizontal: 16,
    paddingVertical: 14,
    borderWidth: 1,
    borderColor: '#E5E7EB',
  },
  input: {
    flex: 1,
    fontSize: 16,
    color: theme.colors.text,
  },
  inputUnit: {
    fontSize: 16,
    color: '#6B7280',
    fontWeight: '500',
  },
  recommendationCard: {
    backgroundColor: '#F0FDF4',
    borderRadius: 16,
    padding: 20,
    marginBottom: 24,
    borderWidth: 1,
    borderColor: '#D1FAE5',
  },
  recommendationHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 12,
  },
  aiIconContainer: {
    width: 32,
    height: 32,
    borderRadius: 16,
    backgroundColor: '#10B981',
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 10,
  },
  recommendationTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: '#065F46',
  },
  recommendationText: {
    fontSize: 14,
    color: '#047857',
    lineHeight: 20,
  },
  bottomSpacer: {
    height: 40,
  },
});
