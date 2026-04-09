import React from 'react';
import { View, Text, StyleSheet, ViewStyle } from 'react-native';
import { Ionicons } from '@expo/vector-icons';

interface AchievementCardProps {
  title: string;
  description?: string;
  icon: string;
  color: string;
  unlocked?: boolean;
  style?: ViewStyle;
  size?: 'small' | 'medium' | 'large';
}

export const AchievementCard: React.FC<AchievementCardProps> = ({
  title,
  description,
  icon,
  color,
  unlocked = true,
  style,
  size = 'medium',
}) => {
  const isDark = color === '#1F2937';
  const iconColor = unlocked
    ? isDark
      ? '#FFFFFF'
      : '#10B981'
    : '#9CA3AF';
  const titleColor = unlocked
    ? isDark
      ? '#FFFFFF'
      : '#065F46'
    : '#6B7280';
  const descriptionColor = unlocked ? '#059669' : '#9CA3AF';

  return (
    <View
      style={[
        styles.container,
        { backgroundColor: unlocked ? color : '#E5E7EB' },
        size === 'small' && styles.containerSmall,
        size === 'large' && styles.containerLarge,
        style,
      ]}
    >
      <View style={[styles.iconContainer, !unlocked && styles.iconLocked]}>
        <Ionicons
          name={icon as any}
          size={size === 'small' ? 32 : size === 'large' ? 48 : 40}
          color={iconColor}
        />
      </View>
      <Text
        style={[
          styles.title,
          { color: titleColor },
          size === 'small' && styles.titleSmall,
          size === 'large' && styles.titleLarge,
        ]}
      >
        {title}
      </Text>
      {description && (
        <Text
          style={[
            styles.description,
            { color: descriptionColor },
            size === 'small' && styles.descriptionSmall,
          ]}
        >
          {description}
        </Text>
      )}
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    borderRadius: 20,
    padding: 20,
    alignItems: 'center',
    justifyContent: 'center',
    minHeight: 140,
  },
  containerSmall: {
    padding: 16,
    minHeight: 120,
  },
  containerLarge: {
    padding: 24,
    minHeight: 160,
  },
  iconContainer: {
    marginBottom: 12,
  },
  iconLocked: {
    opacity: 0.5,
  },
  title: {
    fontSize: 11,
    fontWeight: '700',
    textAlign: 'center',
    letterSpacing: 0.5,
  },
  titleSmall: {
    fontSize: 10,
  },
  titleLarge: {
    fontSize: 12,
  },
  description: {
    fontSize: 10,
    fontWeight: '500',
    textAlign: 'center',
    marginTop: 4,
  },
  descriptionSmall: {
    fontSize: 9,
  },
});
