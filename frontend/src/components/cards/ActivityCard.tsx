import React from 'react';
import { View, Text, StyleSheet, TouchableOpacity, ViewStyle } from 'react-native';
import { Ionicons } from '@expo/vector-icons';

interface ActivityCardProps {
  title: string;
  subtitle: string;
  icon: string;
  iconColor?: string;
  onPress?: () => void;
  style?: ViewStyle;
  dark?: boolean;
}

export const ActivityCard: React.FC<ActivityCardProps> = ({
  title,
  subtitle,
  icon,
  iconColor = '#10B981',
  onPress,
  style,
  dark = false,
}) => {
  return (
    <TouchableOpacity
      style={[
        styles.container, 
        dark && { backgroundColor: '#1E293B' },
        style
      ]}
      onPress={onPress}
      disabled={!onPress}
    >
      <View style={styles.left}>
        <View
          style={[
            styles.iconContainer,
            { backgroundColor: `${iconColor}20` },
          ]}
        >
          <Ionicons name={icon as any} size={24} color={iconColor} />
        </View>
        <View style={styles.info}>
          <Text style={[styles.title, dark && { color: '#F8FAFC' }]}>{title}</Text>
          <Text style={[styles.subtitle, dark && { color: '#94A3B8' }]}>{subtitle}</Text>
        </View>
      </View>
      {onPress && (
        <Ionicons name="chevron-forward" size={20} color={dark ? '#94A3B8' : '#9CA3AF'} />
      )}
    </TouchableOpacity>
  );
};

const styles = StyleSheet.create({
  container: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    backgroundColor: '#FFFFFF',
    borderRadius: 16,
    padding: 16,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 8,
    elevation: 2,
  },
  left: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    flex: 1,
  },
  iconContainer: {
    width: 48,
    height: 48,
    borderRadius: 24,
    justifyContent: 'center',
    alignItems: 'center',
  },
  info: {
    flex: 1,
  },
  title: {
    fontSize: 15,
    fontWeight: '700',
    color: '#1F2937',
    marginBottom: 4,
  },
  subtitle: {
    fontSize: 13,
    color: '#6B7280',
  },
});
