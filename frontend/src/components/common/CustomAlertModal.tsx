import React, { useState, useImperativeHandle, forwardRef, PropsWithChildren } from 'react';
import { 
  View, 
  Text, 
  Modal, 
  StyleSheet, 
  TouchableOpacity, 
  Animated, 
  Easing, 
  TouchableWithoutFeedback 
} from 'react-native';
import { theme } from '../../theme';
import { Ionicons } from '@expo/vector-icons';

export interface AlertButton {
  text?: string;
  onPress?: () => void;
  style?: 'default' | 'cancel' | 'destructive';
}

export interface CustomAlertRef {
  alert: (title: string, message?: string, buttons?: AlertButton[]) => void;
}

export const CustomAlertModal = forwardRef<CustomAlertRef, PropsWithChildren<{}>>((props, ref) => {
  const [visible, setVisible] = useState(false);
  const [title, setTitle] = useState('');
  const [message, setMessage] = useState('');
  const [buttons, setButtons] = useState<AlertButton[]>([]);
  const [fadeAnim] = useState(new Animated.Value(0));
  const [scaleAnim] = useState(new Animated.Value(0.95));

  useImperativeHandle(ref, () => ({
    alert: (newTitle: string, newMessage?: string, newButtons?: AlertButton[]) => {
      setTitle(newTitle);
      setMessage(newMessage || '');
      
      if (!newButtons || newButtons.length === 0) {
        setButtons([{ text: 'OK', style: 'default' }]);
      } else {
        setButtons(newButtons);
      }
      
      setVisible(true);
      
      Animated.parallel([
        Animated.timing(fadeAnim, {
          toValue: 1,
          duration: theme.animation.fast,
          easing: Easing.out(Easing.ease),
          useNativeDriver: true,
        }),
        Animated.spring(scaleAnim, {
          toValue: 1,
          friction: 8,
          tension: 100,
          useNativeDriver: true,
        })
      ]).start();
    }
  }));

  const hide = (onPress?: () => void) => {
    Animated.parallel([
      Animated.timing(fadeAnim, {
        toValue: 0,
        duration: theme.animation.fast,
        easing: Easing.in(Easing.ease),
        useNativeDriver: true,
      }),
      Animated.timing(scaleAnim, {
        toValue: 0.95,
        duration: theme.animation.fast,
        easing: Easing.in(Easing.ease),
        useNativeDriver: true,
      })
    ]).start(() => {
      setVisible(false);
      if (onPress) onPress();
    });
  };

  const isError = title.toLowerCase().includes('error') || title.toLowerCase().includes('failed') || title.toLowerCase().includes('denied') || title.toLowerCase().includes('issue');

  if (!visible) return null;

  return (
    <Modal
      transparent
      visible={visible}
      animationType="none"
      onRequestClose={() => hide()}
    >
      <TouchableWithoutFeedback onPress={() => hide()}>
        <Animated.View style={[styles.backdrop, { opacity: fadeAnim }]}>
          <TouchableWithoutFeedback>
            <Animated.View 
              style={[
                styles.modalContainer, 
                { 
                  opacity: fadeAnim,
                  transform: [{ scale: scaleAnim }]
                }
              ]}
            >
              <View style={styles.header}>
                <View style={[styles.iconContainer, isError ? styles.iconContainerError : styles.iconContainerInfo]}>
                  <Ionicons 
                    name={isError ? "alert-circle" : "information-circle"} 
                    size={28} 
                    color={isError ? theme.colors.error : theme.colors.primary} 
                  />
                </View>
                <Text style={styles.title}>{title}</Text>
              </View>

              {message ? (
                <Text style={styles.message}>{message}</Text>
              ) : null}

              <View style={styles.buttonContainer}>
                {buttons.map((button, index) => {
                  const isCancel = button.style === 'cancel';
                  const isDestructive = button.style === 'destructive';
                  
                  return (
                    <TouchableOpacity
                      key={index}
                      style={[
                        styles.button,
                        isCancel ? styles.buttonCancel : 
                        isDestructive ? styles.buttonDestructive : 
                        styles.buttonDefault,
                        // If multiple buttons, lay them out next to each other
                        buttons.length > 1 && { flex: 1, marginHorizontal: 4 }
                      ]}
                      onPress={() => hide(button.onPress)}
                      activeOpacity={0.7}
                    >
                      <Text 
                        style={[
                          styles.buttonText,
                          isCancel ? styles.buttonTextCancel : 
                          isDestructive ? styles.buttonTextDestructive : 
                          styles.buttonTextDefault
                        ]}
                      >
                        {button.text || 'OK'}
                      </Text>
                    </TouchableOpacity>
                  );
                })}
              </View>
            </Animated.View>
          </TouchableWithoutFeedback>
        </Animated.View>
      </TouchableWithoutFeedback>
    </Modal>
  );
});

const styles = StyleSheet.create({
  backdrop: {
    flex: 1,
    backgroundColor: theme.colors.overlay,
    justifyContent: 'center',
    alignItems: 'center',
    padding: theme.spacing.xl,
  },
  modalContainer: {
    width: '100%',
    maxWidth: 400,
    backgroundColor: theme.colors.surface,
    borderRadius: theme.borderRadius.xl,
    padding: theme.spacing.xl,
    ...theme.shadows.xl,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: theme.spacing.md,
  },
  iconContainer: {
    width: 40,
    height: 40,
    borderRadius: 20,
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: theme.spacing.sm,
  },
  iconContainerError: {
    backgroundColor: theme.colors.errorLight,
  },
  iconContainerInfo: {
    backgroundColor: theme.colors.primaryAlpha,
  },
  title: {
    ...theme.typography.h3,
    fontSize: 15,
    lineHeight: 18,
    color: theme.colors.text,
    flex: 1,
  },
  message: {
    ...theme.typography.body,
    fontSize: 12,
    lineHeight: 16,
    color: theme.colors.textSecondary,
    marginBottom: theme.spacing.lg,
  },
  buttonContainer: {
    flexDirection: 'row',
    justifyContent: 'flex-end',
    width: '100%',
    gap: theme.spacing.sm,
  },
  button: {
    paddingVertical: 8,
    paddingHorizontal: 16,
    borderRadius: theme.borderRadius.md,
    justifyContent: 'center',
    alignItems: 'center',
    minWidth: 80,
  },
  buttonDefault: {
    backgroundColor: theme.colors.primary,
  },
  buttonCancel: {
    backgroundColor: theme.colors.backgroundDark,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  buttonDestructive: {
    backgroundColor: theme.colors.error,
  },
  buttonText: {
    ...theme.typography.bodyMedium,
    fontSize: 12,
  },
  buttonTextDefault: {
    color: theme.colors.textInverse,
  },
  buttonTextCancel: {
    color: theme.colors.text,
  },
  buttonTextDestructive: {
    color: theme.colors.textInverse,
  },
});
