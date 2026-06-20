import React, { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TextInput,
  ScrollView,
  TouchableOpacity,
  Alert,
  KeyboardAvoidingView,
  Platform
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useNavigation, useRoute, RouteProp } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { RootStackParamList } from '../../../navigation/types';
import { ProgressHeader } from '../../../components/common/ProgressHeader';
import { CustomAlert } from '../../../components/common/CustomAlert';
import { Button } from '../../../components/buttons/Button';
import { Ionicons } from '@expo/vector-icons';
import { theme } from '../../../theme';
import { endpoints } from '../../../services/api/apiClient';

type NavigationProp = NativeStackNavigationProp<RootStackParamList, 'Restrictions'>;
type RestrictionsRouteProp = RouteProp<RootStackParamList, 'Restrictions'>;

export const RestrictionsScreen = () => {
  const navigation = useNavigation<NavigationProp>();
  const route = useRoute<RestrictionsRouteProp>();
  const params = route.params;

  const [selectedDiet, setSelectedDiet] = useState<string>('');
  const [selectedAllergies, setSelectedAllergies] = useState<string[]>([]);
  const [selectedConditions, setSelectedConditions] = useState<string[]>([]);
  const [customDiet, setCustomDiet] = useState('');
  const [customAllergy, setCustomAllergy] = useState('');
  const [customCondition, setCustomCondition] = useState('');
  const [isLoading, setIsLoading] = useState(false);

  const dietOptions = [
    { id: 'vegan', label: 'Vegan', icon: 'leaf-outline' },
    { id: 'keto', label: 'Keto', icon: 'fast-food-outline' },
    { id: 'paleo', label: 'Paleo', icon: 'fish-outline' },
    { id: 'low-carb', label: 'Low Carb', icon: 'nutrition-outline' },
    { id: 'vegetarian', label: 'Vegetarian', icon: 'leaf' },
    { id: 'none', label: 'None', icon: 'infinite-outline' },
    { id: 'other', label: 'Other', icon: 'create-outline' },
  ];

  const allergyOptions = [
    'Dairy',
    'Peanuts',
    'Shellfish',
    'Gluten',
    'Soy',
    'Nuts',
    'None',
    'Other',
  ];

  const medicalConditions = [
    {
      id: 'high-blood-pressure',
      title: 'High Blood Pressure',
      description: 'Adjusts workout intensity',
      icon: 'pulse-outline',
    },
    {
      id: 'diabetes',
      title: 'Diabetes',
      description: 'Suggests specialized meal plans',
      icon: 'medical-outline',
    },
    {
      id: 'none',
      title: 'No known conditions',
      description: 'General high-performance plans',
      icon: 'checkmark-circle-outline',
    },
    {
      id: 'other',
      title: 'Other',
      description: 'Please specify',
      icon: 'create-outline',
    },
  ];

  const toggleAllergy = (allergy: string) => {
    if (selectedAllergies.includes(allergy)) {
      setSelectedAllergies(selectedAllergies.filter((a) => a !== allergy));
    } else {
      setSelectedAllergies([...selectedAllergies, allergy]);
    }
  };

  const toggleCondition = (conditionId: string) => {
    if (selectedConditions.includes(conditionId)) {
      setSelectedConditions(selectedConditions.filter((c) => c !== conditionId));
    } else {
      setSelectedConditions([conditionId]);
    }
  };

  const handleFinish = async () => {
    setIsLoading(true);
    
    const finalDiet = selectedDiet === 'other' && customDiet.trim() !== '' ? customDiet.trim() : selectedDiet;
    const finalAllergies = selectedAllergies.includes('Other') && customAllergy.trim() !== ''
      ? [...selectedAllergies.filter(a => a !== 'Other'), customAllergy.trim()]
      : selectedAllergies.filter(a => a !== 'Other');
    const finalConditions = selectedConditions.includes('other') && customCondition.trim() !== ''
      ? [...selectedConditions.filter(c => c !== 'other'), customCondition.trim()]
      : selectedConditions.filter(c => c !== 'other');

    try {
      // Check if paywall is enabled before deciding where to navigate next
      let monetizationOn = true;
      try {
        const statusRes = await fetch(endpoints.getSystemStatus);
        const statusData = await statusRes.json();
        monetizationOn = statusData?.data?.monetization_enabled === true;
      } catch { /* fail closed — show subscription screen if unsure */ }

      // When paywall is OFF: complete onboarding immediately, skip subscription screen
      const nextStep = monetizationOn ? 'SubscriptionSelection' : 'Complete';

      const profilePayload = {
        ...params,
        selectedDiet: finalDiet,
        selectedAllergies: finalAllergies,
        selectedConditions: finalConditions,
        survey_step: nextStep,
        ...(nextStep === 'Complete' ? { subscription_tier: 'free' } : {}),
      };

      const response = await fetch(endpoints.saveProfile, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(profilePayload),
      });

      const data = await response.json();

      if (response.ok && data.status === 'success') {
        // Sync progress state to AsyncStorage local session
        try {
          const savedSession = await AsyncStorage.getItem('user_session');
          if (savedSession) {
            const session = JSON.parse(savedSession);
            session.surveyStep = nextStep;
            if (!session.profile) session.profile = {};
            session.profile.selectedDiet = finalDiet;
            session.profile.selectedAllergies = finalAllergies;
            session.profile.selectedConditions = finalConditions;
            if (nextStep === 'Complete') {
              session.profile.subscriptionTier = 'free';
            }
            await AsyncStorage.setItem('user_session', JSON.stringify(session));
          }
        } catch (err) {
          console.error('Failed to sync session step:', err);
        }

        if (monetizationOn) {
          navigation.navigate('SubscriptionSelection', { firstName: params.firstName, userId: params.userId });
        } else {
          navigation.navigate('Main', { firstName: params.firstName, userId: params.userId });
        }
      } else {
        CustomAlert.alert('Error', data.message || 'Failed to save profile');
      }
    } catch (error) {
      CustomAlert.alert('Network Error', 'Could not connect to the server.');
      console.error(error);
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <SafeAreaView style={styles.container}>
      <KeyboardAvoidingView
        style={styles.keyboardView}
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
        keyboardVerticalOffset={Platform.OS === 'ios' ? 100 : 20}
      >
        <ScrollView
          style={styles.scrollView}
          contentContainerStyle={styles.scrollContent}
          showsVerticalScrollIndicator={false}
          keyboardShouldPersistTaps="handled"
        >
        {/* Progress Header */}
        <ProgressHeader
          currentStep={3}
          totalSteps={3}
          stepLabel="100%"
          onBackPress={() => navigation.goBack()}
        />

        {/* Title */}
        <Text style={styles.title}>Any restrictions?</Text>
        <Text style={styles.subtitle}>Safety and nutrition come first.</Text>

        {/* Dietary Preferences */}
        <View style={styles.section}>
          <View style={styles.sectionHeader}>
            <Ionicons name="restaurant-outline" size={20} color="#10B981" />
            <Text style={styles.sectionTitle}>Dietary Preferences</Text>
          </View>

          <View style={styles.optionsGrid}>
            {dietOptions.map((option) => (
              <TouchableOpacity
                key={option.id}
                style={[
                  styles.dietCard,
                  selectedDiet === option.id && styles.dietCardSelected,
                ]}
                onPress={() => setSelectedDiet(option.id)}
              >
                <Ionicons
                  name={option.icon as any}
                  size={28}
                  color={selectedDiet === option.id ? '#10B981' : '#6B7280'}
                />
                <Text
                  style={[
                    styles.dietLabel,
                    selectedDiet === option.id && styles.dietLabelSelected,
                  ]}
                >
                  {option.label}
                </Text>
              </TouchableOpacity>
            ))}
          </View>
          {selectedDiet === 'other' && (
            <TextInput
              style={styles.customInput}
              placeholder="Please specify dietary preference"
              value={customDiet}
              onChangeText={setCustomDiet}
              placeholderTextColor="#9CA3AF"
            />
          )}
        </View>

        {/* Allergies */}
        <View style={styles.section}>
          <View style={styles.sectionHeader}>
            <Ionicons name="warning-outline" size={20} color="#10B981" />
            <Text style={styles.sectionTitle}>Allergies</Text>
          </View>

          <View style={styles.pillsContainer}>
            {allergyOptions.map((allergy) => (
              <TouchableOpacity
                key={allergy}
                style={[
                  styles.allergyPill,
                  selectedAllergies.includes(allergy) && styles.allergyPillSelected,
                ]}
                onPress={() => toggleAllergy(allergy)}
              >
                <Text
                  style={[
                    styles.allergyPillText,
                    selectedAllergies.includes(allergy) && styles.allergyPillTextSelected,
                  ]}
                >
                  {allergy}
                </Text>
              </TouchableOpacity>
            ))}
          </View>
          {selectedAllergies.includes('Other') && (
            <TextInput
              style={styles.customInput}
              placeholder="Please specify allergy"
              value={customAllergy}
              onChangeText={setCustomAllergy}
              placeholderTextColor="#9CA3AF"
            />
          )}
        </View>

        {/* Medical Conditions */}
        <View style={styles.section}>
          <View style={styles.sectionHeader}>
            <Ionicons name="fitness-outline" size={20} color="#10B981" />
            <Text style={styles.sectionTitle}>Medical Conditions</Text>
          </View>

          <View style={styles.conditionsContainer}>
            {medicalConditions.map((condition) => (
              <TouchableOpacity
                key={condition.id}
                style={[
                  styles.conditionCard,
                  selectedConditions.includes(condition.id) && styles.conditionCardSelected,
                ]}
                onPress={() => toggleCondition(condition.id)}
              >
                <View style={styles.conditionLeft}>
                  <View
                    style={[
                      styles.conditionIconContainer,
                      selectedConditions.includes(condition.id) &&
                        styles.conditionIconContainerSelected,
                    ]}
                  >
                    <Ionicons
                      name={condition.icon as any}
                      size={24}
                      color={
                        selectedConditions.includes(condition.id) ? '#10B981' : '#6B7280'
                      }
                    />
                  </View>
                  <View style={styles.conditionInfo}>
                    <Text style={styles.conditionTitle}>{condition.title}</Text>
                    <Text style={styles.conditionDescription}>{condition.description}</Text>
                  </View>
                </View>
                {selectedConditions.includes(condition.id) && (
                  <View style={styles.checkmarkContainer}>
                    <Ionicons name="checkmark" size={20} color="#10B981" />
                  </View>
                )}
              </TouchableOpacity>
            ))}
          </View>
          {selectedConditions.includes('other') && (
            <TextInput
              style={styles.customInput}
              placeholder="Please specify medical condition"
              value={customCondition}
              onChangeText={setCustomCondition}
              placeholderTextColor="#9CA3AF"
            />
          )}
        </View>

        <Button
          title={isLoading ? "Saving..." : "Get Started"}
          onPress={handleFinish}
          disabled={isLoading}
          icon={<Ionicons name="checkmark" size={20} color="#FFFFFF" />}
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
    marginBottom: 32,
  },
  section: {
    marginBottom: 32,
  },
  sectionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 16,
    gap: 8,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: theme.colors.text,
  },
  optionsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
  },
  dietCard: {
    width: '48%',
    backgroundColor: '#FFFFFF',
    borderRadius: 16,
    padding: 16,
    alignItems: 'center',
    borderWidth: 2,
    borderColor: '#E5E7EB',
    gap: 8,
  },
  dietCardSelected: {
    borderColor: '#10B981',
    backgroundColor: '#F0FDF4',
  },
  dietLabel: {
    fontSize: 13,
    fontWeight: '600',
    color: '#6B7280',
  },
  dietLabelSelected: {
    color: '#10B981',
  },
  pillsContainer: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
  },
  allergyPill: {
    paddingHorizontal: 20,
    paddingVertical: 12,
    borderRadius: 20,
    backgroundColor: '#FFFFFF',
    borderWidth: 2,
    borderColor: '#E5E7EB',
  },
  allergyPillSelected: {
    backgroundColor: '#F0FDF4',
    borderColor: '#10B981',
  },
  allergyPillText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#6B7280',
  },
  allergyPillTextSelected: {
    color: '#10B981',
  },
  conditionsContainer: {
    gap: 12,
  },
  conditionCard: {
    backgroundColor: '#FFFFFF',
    borderRadius: 16,
    padding: 16,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    borderWidth: 2,
    borderColor: '#E5E7EB',
  },
  conditionCardSelected: {
    borderColor: '#10B981',
    backgroundColor: '#F0FDF4',
  },
  conditionLeft: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
    gap: 12,
  },
  conditionIconContainer: {
    width: 48,
    height: 48,
    borderRadius: 24,
    backgroundColor: '#F3F4F6',
    justifyContent: 'center',
    alignItems: 'center',
  },
  conditionIconContainerSelected: {
    backgroundColor: '#D1FAE5',
  },
  conditionInfo: {
    flex: 1,
  },
  conditionTitle: {
    fontSize: 15,
    fontWeight: '700',
    color: theme.colors.text,
    marginBottom: 2,
  },
  conditionDescription: {
    fontSize: 13,
    color: '#6B7280',
  },
  checkmarkContainer: {
    width: 32,
    height: 32,
    borderRadius: 16,
    backgroundColor: '#D1FAE5',
    justifyContent: 'center',
    alignItems: 'center',
  },
  bottomSpacer: {
    height: 120,
  },
  customInput: {
    marginTop: 16,
    borderWidth: 2,
    borderColor: '#E5E7EB',
    borderRadius: 12,
    padding: 16,
    fontSize: 16,
    color: theme.colors.text,
    backgroundColor: '#FFFFFF',
  },
});
