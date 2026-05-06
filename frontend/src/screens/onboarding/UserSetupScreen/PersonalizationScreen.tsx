import { SafeAreaView } from 'react-native-safe-area-context';
import { CustomAlert } from '../../../components/common/CustomAlert';
import React, { useState } from 'react';
import { View, Text, StyleSheet,  ScrollView, TouchableOpacity, Dimensions, TextInput, KeyboardAvoidingView, Platform, Alert } from 'react-native';
import Slider from '@react-native-community/slider';
import { useNavigation, useRoute, RouteProp } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { RootStackParamList } from '../../../navigation/AppNavigator';
import { Button } from '../../../components/buttons/Button';
import { Input } from '../../../components/inputs/Input';
import { ProgressHeader } from '../../../components/common/ProgressHeader';
import { Ionicons } from '@expo/vector-icons';
import { theme } from '../../../theme';
import { endpoints } from '../../../services/api/apiClient';

const { width } = Dimensions.get('window');

type NavigationProp = NativeStackNavigationProp<RootStackParamList, 'Personalization'>;
type PersonalizationRouteProp = RouteProp<RootStackParamList, 'Personalization'>;

export const PersonalizationScreen = () => {
  const navigation = useNavigation<NavigationProp>();
  const route = useRoute<PersonalizationRouteProp>();
  const { userId, firstName } = route.params;
  
  const [age, setAge] = useState('');
  const [height, setHeight] = useState('175');
  const [weight, setWeight] = useState('72');
  const [gender, setGender] = useState('M');
  const [activity, setActivity] = useState('Moderate');
  const [hasEquipment, setHasEquipment] = useState(true);

  const renderPill = (label: string, value: string, currentValue: string, setter: (val: string) => void) => {
    const isSelected = value === currentValue;
    return (
      <TouchableOpacity
        style={[styles.pill, isSelected && styles.pillSelected]}
        onPress={() => setter(value)}
      >
        <Text style={[styles.pillText, isSelected && styles.pillTextSelected]}>{label}</Text>
      </TouchableOpacity>
    );
  };

  const handleNext = async () => {
    try {
      const response = await fetch(endpoints.saveProfile, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          userId, age, gender, height, weight, activityLevel: activity, hasEquipment, survey_step: 'GoalSetting', firstName
        }),
      });
      const data = await response.json();
      if (response.ok && data.status === 'success') {
        navigation.navigate('GoalSetting', {
          userId, age, gender, height, weight, activityLevel: activity, firstName, hasEquipment
        });
      } else {
        CustomAlert.alert('Error', data.message || 'Failed to save progress');
      }
    } catch (e) {
      CustomAlert.alert('Error', 'Network request failed');
    }
  };

  return (
    <SafeAreaView style={styles.container}>
      <KeyboardAvoidingView 
        style={styles.keyboardView}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      >
        <ScrollView contentContainerStyle={styles.scrollContent} showsVerticalScrollIndicator={false} keyboardShouldPersistTaps="handled">
        
        {/* Progress Header */}
        <ProgressHeader
          currentStep={1}
          totalSteps={3}
          stepLabel="Basic Info"
        />

        {/* Titles */}
        <Text style={styles.title}>Tell us about yourself</Text>
        <Text style={styles.subtitle}>This helps us create your custom plan.</Text>

        {/* Age & Gender Row */}
        <View style={styles.row}>
          <View style={styles.halfWidth}>
            <Input 
              label="AGE" 
              placeholder="25" 
              keyboardType="numeric" 
              value={age}
              onChangeText={setAge}
            />
          </View>
          <View style={styles.halfWidth}>
            <Text style={styles.label}>GENDER</Text>
            <View style={styles.pillGroup}>
              {renderPill('M', 'M', gender, setGender)}
              {renderPill('F', 'F', gender, setGender)}
              {renderPill('O', 'O', gender, setGender)}
            </View>
          </View>
        </View>

        {/* Mock Sliders */}
        <View style={styles.sliderSection}>
          <View style={styles.sliderHeader}>
            <Text style={styles.label}>HEIGHT</Text>
            <View style={{ flexDirection: 'row', alignItems: 'baseline' }}>
              <TextInput style={styles.sliderValue} value={height} onChangeText={setHeight} keyboardType="numeric" />
              <Text style={styles.sliderUnit}> cm</Text>
            </View>
          </View>
          <Slider
            style={{width: '100%', height: 40}}
            minimumValue={100}
            maximumValue={250}
            step={1}
            value={parseFloat(height) || 175}
            onValueChange={(val) => setHeight(val.toString())}
            minimumTrackTintColor={theme.colors.primary}
            maximumTrackTintColor={theme.colors.surface}
            thumbTintColor={theme.colors.primary}
          />
        </View>

        <View style={styles.sliderSection}>
          <View style={styles.sliderHeader}>
            <Text style={styles.label}>WEIGHT</Text>
            <View style={{ flexDirection: 'row', alignItems: 'baseline' }}>
              <TextInput style={styles.sliderValue} value={weight} onChangeText={setWeight} keyboardType="numeric" />
              <Text style={styles.sliderUnit}> kg</Text>
            </View>
          </View>
          <Slider
            style={{width: '100%', height: 40}}
            minimumValue={40}
            maximumValue={150}
            step={1}
            value={parseFloat(weight) || 72}
            onValueChange={(val) => setWeight(val.toString())}
            minimumTrackTintColor={theme.colors.primary}
            maximumTrackTintColor={theme.colors.surface}
            thumbTintColor={theme.colors.primary}
          />
        </View>

        {/* Activity Level */}
        <View style={styles.section}>
          <Text style={styles.label}>ACTIVITY LEVEL</Text>
          <View style={styles.pillRow}>
            {renderPill('Sedentary', 'Sedentary', activity, setActivity)}
            {renderPill('Moderate', 'Moderate', activity, setActivity)}
            {renderPill('Active', 'Active', activity, setActivity)}
          </View>
        </View>

        
        {/* Equipment Status */}
        <View style={styles.section}>
          <Text style={styles.label}>DO YOU HAVE EQUIPMENT?</Text>
          <View style={styles.equipmentRow}>
            <TouchableOpacity 
              style={[styles.equipmentCard, !hasEquipment && styles.equipmentCardSelected]}
              onPress={() => setHasEquipment(false)}
            >
              <Ionicons name="body" size={24} color={!hasEquipment ? theme.colors.primary : theme.colors.textSecondary} />
              <View style={styles.equipmentTextContainer}>
                <Text style={[styles.equipmentTitle, !hasEquipment && styles.equipmentTitleSelected]}>Bodyweight Only</Text>
                <Text style={styles.equipmentSubtitle}>No tools needed</Text>
              </View>
            </TouchableOpacity>

            <TouchableOpacity 
              style={[styles.equipmentCard, hasEquipment && styles.equipmentCardSelected]}
              onPress={() => setHasEquipment(true)}
            >
              <Ionicons name="barbell" size={24} color={hasEquipment ? theme.colors.primary : theme.colors.textSecondary} />
              <View style={styles.equipmentTextContainer}>
                <Text style={[styles.equipmentTitle, hasEquipment && styles.equipmentTitleSelected]}>Full Access</Text>
                <Text style={styles.equipmentSubtitle}>Dumbbells, etc.</Text>
              </View>
            </TouchableOpacity>
          </View>
        </View>

        {/* Next Button */}
        <View style={styles.footer}>
          <Button 
            title="Next Step " 
            onPress={handleNext} 
          />
        </View>

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
  scrollContent: {
    flexGrow: 1,
    paddingHorizontal: theme.spacing.lg,
    paddingTop: theme.spacing.xxxl,
    paddingBottom: theme.spacing.xl,
  },
  progressHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: theme.spacing.xxl,
  },
  stepText: {
    ...theme.typography.bodySmall,
    color: theme.colors.textSecondary,
    marginBottom: 4,
  },
  progressBarContainer: {
    height: 4,
    width: 60,
    backgroundColor: theme.colors.surface,
    borderRadius: 2,
  },
  progressBarFill: {
    height: '100%',
    width: '33%',
    backgroundColor: theme.colors.primary,
    borderRadius: 2,
  },
  progressStepName: {
    ...theme.typography.bodySmall,
    color: theme.colors.primary,
    fontWeight: '600',
  },
  title: {
    ...theme.typography.h2,
    marginBottom: theme.spacing.xs,
  },
  subtitle: {
    ...theme.typography.body,
    color: theme.colors.textSecondary,
    marginBottom: theme.spacing.xl,
  },
  row: {
    flexDirection: 'row',
    gap: theme.spacing.lg,
    marginBottom: theme.spacing.lg,
  },
  halfWidth: {
    flex: 1,
  },
  label: {
    ...theme.typography.bodySmall,
    fontWeight: '600',
    color: theme.colors.textSecondary,
    marginBottom: theme.spacing.sm,
  },
  pillGroup: {
    flexDirection: 'row',
    backgroundColor: theme.colors.surface,
    borderRadius: theme.borderRadius.full,
    height: 56,
    padding: 4,
  },
  pillRow: {
    flexDirection: 'row',
    gap: theme.spacing.sm,
  },
  pill: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    borderRadius: theme.borderRadius.full,
    paddingVertical: theme.spacing.sm,
    paddingHorizontal: theme.spacing.md,
    backgroundColor: theme.colors.surface,
    borderWidth: 1,
    borderColor: theme.colors.surface,
  },
  pillSelected: {
    backgroundColor: theme.colors.background,
    borderColor: theme.colors.primary,
    shadowColor: theme.colors.primary,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 2,
  },
  pillText: {
    ...theme.typography.bodySmall,
    fontWeight: '600',
    fontSize:13,
    color: theme.colors.textSecondary,
  },
  pillTextSelected: {
    color: theme.colors.text,
  },
  sliderSection: {
    marginBottom: theme.spacing.xl,
  },
  sliderHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'baseline',
    marginBottom: theme.spacing.md,
  },
  sliderValue: {
    ...theme.typography.h3,
  },
  sliderUnit: {
    ...theme.typography.bodySmall,
    color: theme.colors.textSecondary,
  },
  sliderTrack: {
    height: 4,
    backgroundColor: theme.colors.surface,
    borderRadius: 2,
    position: 'relative',
    justifyContent: 'center',
  },
  sliderFill: {
    position: 'absolute',
    height: '100%',
    backgroundColor: theme.colors.primary,
    borderRadius: 2,
    left: 0,
  },
  sliderThumb: {
    position: 'absolute',
    width: 24,
    height: 24,
    borderRadius: 12,
    backgroundColor: theme.colors.primary,
    marginLeft: -12, // center the thumb
  },
  section: {
    marginBottom: theme.spacing.xl,
  },
  goalCard: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: theme.spacing.lg,
    backgroundColor: theme.colors.surface,
    borderRadius: theme.borderRadius.lg,
    marginBottom: theme.spacing.md,
    borderWidth: 1,
    borderColor: 'transparent',
  },
  goalCardSelected: {
    backgroundColor: theme.colors.background,
    borderColor: theme.colors.primary,
    shadowColor: theme.colors.primary,
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.05,
    shadowRadius: 8,
    elevation: 4,
  },
  goalIconContainer: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: theme.colors.background,
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: theme.spacing.md,
  },
  goalIconContainerSelected: {
    backgroundColor: theme.colors.surface,
  },
  goalText: {
    ...theme.typography.body,
    fontWeight: '600',
    flex: 1,
  },
  equipmentRow: {
    flexDirection: 'row',
    gap: theme.spacing.md,
  },
  equipmentCard: {
    flex: 1,
    backgroundColor: theme.colors.surface,
    padding: theme.spacing.md,
    borderRadius: theme.borderRadius.lg,
    borderWidth: 1,
    borderColor: 'transparent',
    alignItems: 'center',
    gap: 8,
  },
  equipmentCardSelected: {
    backgroundColor: theme.colors.background,
    borderColor: theme.colors.primary,
  },
  equipmentTextContainer: {
    alignItems: 'center',
  },
  equipmentTitle: {
    fontSize: 14,
    fontWeight: '700',
    color: theme.colors.textSecondary,
    textAlign: 'center',
  },
  equipmentTitleSelected: {
    color: theme.colors.text,
  },
  equipmentSubtitle: {
    fontSize: 10,
    color: theme.colors.textSecondary,
    textAlign: 'center',
  },
  footer: {
    marginTop: theme.spacing.xs,
  },
});
