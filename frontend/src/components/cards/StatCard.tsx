import React from 'react';
import { View, Text, StyleSheet, ViewStyle } from 'react-native';
import { Ionicons } from '@expo/vector-icons';

interface StatCardProps {
  label: string;
  value: string;
  unit?: string;
  icon?: string;
  highlighted?: boolean;
  style?: ViewStyle;
}

export const StatCard: React.FC<StatCardProps> = ({
  label,
  value,
  unit,
  icon,
  highlighted = false,
  style,
}) => {
  return (
    <View style={[styles.container, highlighted && styles.highlighted, style]}>
      <Text style={styles.label}>{label}</Text>
      <View style={styles.valueContainer}>
        <Text style={[styles.value, highlighted && styles.valueLarge]}>
          {value}
        </Text>
        {unit && <Text style={styles.unit}>{unit}</Text>}
        {icon && (
          <Ionicons name={icon as any} size={20} color="#10B981" />
        )}
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#FFFFFF',
    borderRadius: 16,
    padding: 16,
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 8,
    elevation: 2,
  },
  highlighted: {
    backgroundColor: '#D1FAE5',
    borderWidth: 2,
    borderColor: '#10B981',
  },
  label: {
    fontSize: 10,
    fontWeight: '700',
    color: '#6B7280',
    textAlign: 'center',
    marginBottom: 8,
    lineHeight: 12,
  },
  valueContainer: {
    flexDirection: 'row',
    alignItems: 'baseline',
    gap: 2,
  },
  value: {
    fontSize: 28,
    fontWeight: 'bold',
    color: '#1F2937',
  },
  valueLarge: {
    fontSize: 32,
  },
  unit: {
    fontSize: 16,
    fontWeight: '600',
    color: '#6B7280',
  },
});
