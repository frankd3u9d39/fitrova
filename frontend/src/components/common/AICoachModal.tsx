import React from 'react';
import { View, Text, StyleSheet, Modal, TouchableOpacity, Dimensions } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { theme } from '../../theme';
import { Notification } from '../../services/api/notificationService';

interface AICoachModalProps {
  visible: boolean;
  notification: Notification | null;
  onDismiss: () => void;
}

export const AICoachModal: React.FC<AICoachModalProps> = ({ visible, notification, onDismiss }) => {
  if (!notification) return null;

  const getStyleConfigs = (type: Notification['insight_type']) => {
    switch (type) {
      case 'warning':
        return {
          title: 'DIETARY ALERT',
          icon: 'alert-circle',
          iconColor: '#F59E0B',
          bgColor: '#FEF3C7',
          borderColor: '#FDE68A',
          btnColor: '#D97706',
          textColor: '#92400E'
        };
      case 'achievement':
        return {
          title: 'ACHIEVEMENT UNLOCKED',
          icon: 'trophy',
          iconColor: '#6366F1',
          bgColor: '#EEF2FF',
          borderColor: '#C7D2FE',
          btnColor: '#4F46E5',
          textColor: '#3730A3'
        };
      case 'motivation':
        return {
          title: 'DAILY MOTIVATION',
          icon: 'flame',
          iconColor: '#EC4899',
          bgColor: '#FDF2F8',
          borderColor: '#FBCFE8',
          btnColor: '#DB2777',
          textColor: '#9D174D'
        };
      case 'tip':
      default:
        return {
          title: 'AI COACH TIP',
          icon: 'bulb',
          iconColor: '#10B981',
          bgColor: '#ECFDF5',
          borderColor: '#A7F3D0',
          btnColor: '#059669',
          textColor: '#065F46'
        };
    }
  };

  const config = getStyleConfigs(notification.insight_type);

  return (
    <Modal
      transparent
      visible={visible}
      animationType="fade"
      onRequestClose={onDismiss}
    >
      <View style={styles.overlay}>
        <View style={styles.modalContainer}>
          <View style={[styles.card, { backgroundColor: '#FFFFFF', borderColor: config.borderColor }]}>
            {/* Modal Glow Header Accent */}
            <View style={[styles.glowHeader, { backgroundColor: config.bgColor }]} />
            
            {/* Premium Icon Badge */}
            <View style={[styles.iconWrapper, { backgroundColor: config.bgColor, borderColor: config.borderColor }]}>
              <Ionicons name={config.icon as any} size={36} color={config.iconColor} />
            </View>

            {/* Content Container */}
            <View style={styles.content}>
              <Text style={[styles.tag, { color: config.textColor }]}>{config.title}</Text>
              
              <Text style={styles.mainTitle}>Message from AI Coach</Text>
              
              <Text style={styles.bodyText}>
                {notification.insight_text}
              </Text>
            </View>

            {/* Action Buttons */}
            <View style={styles.actions}>
              <TouchableOpacity 
                style={[styles.primaryBtn, { backgroundColor: config.btnColor }]}
                onPress={onDismiss}
                activeOpacity={0.85}
              >
                <Text style={styles.primaryBtnText}>Got it, Coach!</Text>
                <Ionicons name="sparkles-sharp" size={16} color="#FFFFFF" />
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </View>
    </Modal>
  );
};

const { width } = Dimensions.get('window');

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(15, 23, 42, 0.75)', // Elegant semi-dark backdrop
    justifyContent: 'center',
    alignItems: 'center',
    padding: theme.spacing.xl,
  },
  modalContainer: {
    width: '100%',
    maxWidth: width * 0.9,
    alignItems: 'center',
  },
  card: {
    width: '100%',
    borderRadius: 28,
    borderWidth: 1.5,
    overflow: 'hidden',
    padding: theme.spacing.xl,
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.15,
    shadowRadius: 25,
    elevation: 10,
  },
  glowHeader: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    height: 10,
  },
  iconWrapper: {
    width: 72,
    height: 72,
    borderRadius: 36,
    borderWidth: 2,
    justifyContent: 'center',
    alignItems: 'center',
    marginTop: theme.spacing.sm,
    marginBottom: theme.spacing.md,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.05,
    shadowRadius: 10,
  },
  content: {
    alignItems: 'center',
    marginBottom: theme.spacing.xl,
    gap: theme.spacing.sm,
  },
  tag: {
    fontSize: 11,
    fontWeight: '800',
    letterSpacing: 1.5,
    textTransform: 'uppercase',
  },
  mainTitle: {
    ...theme.typography.h3,
    fontWeight: '800',
    color: theme.colors.text,
    textAlign: 'center',
  },
  bodyText: {
    ...theme.typography.body,
    color: theme.colors.textSecondary,
    textAlign: 'center',
    lineHeight: 22,
    marginTop: theme.spacing.xs,
    paddingHorizontal: theme.spacing.sm,
  },
  actions: {
    width: '100%',
    gap: theme.spacing.sm,
  },
  primaryBtn: {
    height: 54,
    borderRadius: 16,
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
    gap: 8,
    width: '100%',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.1,
    shadowRadius: 10,
  },
  primaryBtnText: {
    color: '#FFFFFF',
    fontSize: 16,
    fontWeight: '700',
    letterSpacing: 0.5,
  },
});
