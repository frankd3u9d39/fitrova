import React, { useState } from 'react';
import { View, Text, StyleSheet, SafeAreaView, ScrollView, TouchableOpacity, Dimensions } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { RootStackParamList } from '../navigation/AppNavigator';
import { Button } from '../components/buttons/Button';
import { Input } from '../components/inputs/Input';
import { Ionicons } from '@expo/vector-icons';
import { theme } from '../theme';

const { width } = Dimensions.get('window');

type NavigationProp = NativeStackNavigationProp<RootStackParamList, 'Main'>;

export const PersonalizationScreen = () => {
  const navigation = useNavigation<NavigationProp>();
  
  const [gender, setGender] = useState('M');
  const [activity, setActivity] = useState('Moderate');
  const [goal, setGoal] = useState('Gain Muscle');

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

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView contentContainerStyle={styles.scrollContent} showsVerticalScrollIndicator={false}>
        
        {/* Progress Header */}
        <View style={styles.progressHeader}>
          <View>
            <Text style={styles.stepText}>Step 1 of 3</Text>
            <View style={styles.progressBarContainer}>
              <View style={styles.progressBarFill} />
            </View>
          </View>
          <Text style={styles.progressStepName}>Personalization</Text>
        </View>

        {/* Titles */}
        <Text style={styles.title}>Tell us about yourself</Text>
        <Text style={styles.subtitle}>This helps us create your custom plan.</Text>

        {/* Age & Gender Row */}
        <View style={styles.row}>
          <View style={styles.halfWidth}>
            <Input label="AGE" placeholder="25" keyboardType="numeric" />
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
            <Text style={styles.sliderValue}>175 <Text style={styles.sliderUnit}>cm</Text></Text>
          </View>
          <View style={styles.sliderTrack}>
            <View style={[styles.sliderFill, { width: '60%' }]} />
            <View style={[styles.sliderThumb, { left: '60%' }]} />
          </View>
        </View>

        <View style={styles.sliderSection}>
          <View style={styles.sliderHeader}>
            <Text style={styles.label}>WEIGHT</Text>
            <Text style={styles.sliderValue}>72 <Text style={styles.sliderUnit}>kg</Text></Text>
          </View>
          <View style={styles.sliderTrack}>
            <View style={[styles.sliderFill, { width: '45%' }]} />
            <View style={[styles.sliderThumb, { left: '45%' }]} />
          </View>
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

        {/* Fitness Goal */}
        <View style={styles.section}>
          <Text style={styles.label}>FITNESS GOAL</Text>
          
          <TouchableOpacity 
            style={[styles.goalCard, goal === 'Lose Weight' && styles.goalCardSelected]}
            onPress={() => setGoal('Lose Weight')}
          >
            <View style={styles.goalIconContainer}><Ionicons name="trending-down" size={20} color={theme.colors.text} /></View>
            <Text style={styles.goalText}>Lose Weight</Text>
            {goal === 'Lose Weight' && <Ionicons name="checkmark-circle" size={24} color={theme.colors.primary} />}
          </TouchableOpacity>

          <TouchableOpacity 
            style={[styles.goalCard, goal === 'Gain Muscle' && styles.goalCardSelected]}
            onPress={() => setGoal('Gain Muscle')}
          >
           <View style={[styles.goalIconContainer, goal === 'Gain Muscle' && styles.goalIconContainerSelected]}><Ionicons name="barbell-outline" size={20} color={theme.colors.text} /></View>
            <Text style={styles.goalText}>Gain Muscle</Text>
            {goal === 'Gain Muscle' && <Ionicons name="checkmark-circle" size={24} color={theme.colors.primary} />}
          </TouchableOpacity>
        </View>

        {/* Next Button */}
        <View style={styles.footer}>
          <Button 
            title="Next Step " 
            onPress={() => navigation.navigate('Main')} 
          />
        </View>

      </ScrollView>
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.background,
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
  footer: {
    marginTop: theme.spacing.xs,
  },
});
