import React from 'react';
import { TouchableOpacity, Text, StyleSheet, ActivityIndicator, ViewStyle, TextStyle } from 'react-native';
import { theme } from '../../theme';

interface ButtonProps {
  title: string;
  onPress: () => void;
  variant?: 'primary' | 'secondary' | 'outline' | 'ghost';
  disabled?: boolean;
  loading?: boolean;
  style?: ViewStyle | ViewStyle[];
  textStyle?: TextStyle | TextStyle[];
  icon?: React.ReactNode;
}

export const Button = ({
  title,
  onPress,
  variant = 'primary',
  disabled = false,
  loading = false,
  style,
  textStyle,
  icon,
}: ButtonProps) => {
  const getVariantStyles = () => {
    switch (variant) {
      case 'secondary':
        return {
          button: styles.secondary,
          text: styles.secondaryText,
        };
      case 'outline':
        return {
          button: styles.outline,
          text: styles.outlineText,
        };
      case 'ghost':
        return {
          button: styles.ghost,
          text: styles.ghostText,
        };
      default:
        return {
          button: styles.primary,
          text: styles.primaryText,
        };
    }
  };

  const currentStyles = getVariantStyles();

  return (
    <TouchableOpacity
      style={[
        styles.button,
        currentStyles.button,
        disabled && styles.disabled,
        style,
      ]}
      onPress={onPress}
      disabled={disabled || loading}
      activeOpacity={0.8}
    >
      {loading ? (
        <ActivityIndicator color={currentStyles.text.color as string} />
      ) : (
        <>
          {icon && <>{icon}</>}
          <Text style={[styles.text, currentStyles.text, textStyle]}>
            {title}
          </Text>
        </>
      )}
    </TouchableOpacity>
  );
};

const styles = StyleSheet.create({
  button: {
    height: 56,
    borderRadius: theme.borderRadius.full,
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: theme.spacing.xl,
    gap: theme.spacing.sm,
  },
  text: {
    ...theme.typography.button,
  },
  primary: {
    backgroundColor: theme.colors.primary,
    shadowColor: theme.colors.primary,
    shadowOffset: {
      width: 0,
      height: 4,
    },
    shadowOpacity: 0.3,
    shadowRadius: 8,
    elevation: 4,
  },
  primaryText: {
    color: '#ffffff',
  },
  secondary: {
    backgroundColor: theme.colors.surface,
  },
  secondaryText: {
    color: theme.colors.text,
  },
  outline: {
    backgroundColor: 'transparent',
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  outlineText: {
    color: theme.colors.textSecondary,
  },
  ghost: {
    backgroundColor: 'transparent',
    height: 'auto',
    paddingHorizontal: 0,
  },
  ghostText: {
    color: theme.colors.primary,
  },
  disabled: {
    opacity: 0.5,
  },
});
