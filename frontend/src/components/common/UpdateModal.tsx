import React, { useEffect, useState } from 'react';
import { StyleSheet, View, Text, Modal, TouchableOpacity, Linking, ActivityIndicator } from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import Constants from 'expo-constants';
import { theme } from '../../theme';
import { getAppUpdate, updateVersionSeen, AppUpdateData } from '../../services/api/systemService';

interface UpdateModalProps {
  userId?: number;
}

export const UpdateModal: React.FC<UpdateModalProps> = ({ userId }) => {
  const [visible, setVisible] = useState(false);
  const [updateInfo, setUpdateInfo] = useState<AppUpdateData | null>(null);
  const [loading, setLoading] = useState(false);

  // Safe SemVer version comparison helper
  const isNewerVersion = (current: string, latest: string): boolean => {
    const curParts = current.replace(/[^0-9.]/g, '').split('.').map(Number);
    const latParts = latest.replace(/[^0-9.]/g, '').split('.').map(Number);
    
    const maxLength = Math.max(curParts.length, latParts.length);
    for (let i = 0; i < maxLength; i++) {
      const cur = curParts[i] || 0;
      const lat = latParts[i] || 0;
      if (lat > cur) return true;
      if (cur > lat) return false;
    }
    return false;
  };

  useEffect(() => {
    const checkForUpdates = async () => {
      try {
        setLoading(true);
        // 1. Fetch current installed app version
        const currentVersion = Constants.expoConfig?.version || '1.0.0';
        
        // 2. Fetch latest active version from server
        const updateData = await getAppUpdate();
        if (!updateData || !updateData.is_active) {
          setVisible(false);
          return;
        }

        // 3. Compare versions
        const hasUpdate = isNewerVersion(currentVersion, updateData.version);
        if (!hasUpdate) {
          setVisible(false);
          return;
        }

        // 4. If update is not forced, check if user already dismissed this specific version
        if (!updateData.force_update) {
          const dismissedVersion = await AsyncStorage.getItem('dismissed_update_version');
          if (dismissedVersion === updateData.version) {
            setVisible(false);
            return;
          }
        }

        // 5. Display update alert
        setUpdateInfo(updateData);
        setVisible(true);
      } catch (error) {
        console.warn('⚠️ Error during update check:', error);
      } finally {
        setLoading(false);
      }
    };

    checkForUpdates();
  }, []);

  const handleUpdate = () => {
    // Open app download page (using a mock website link or App Store/Play Store fallback)
    const storeUrl = 'https://fitrova-backend.onrender.com/download'; // placeholder download url
    Linking.openURL(storeUrl).catch(err => console.error("Couldn't load page", err));
  };

  const handleDismiss = async () => {
    if (!updateInfo) return;
    try {
      // 1. Save seen status locally
      await AsyncStorage.setItem('dismissed_update_version', updateInfo.version);
      
      // 2. Sync to DB if logged in
      if (userId) {
        await updateVersionSeen(userId, updateInfo.version);
      }
      
      setVisible(false);
    } catch (error) {
      console.warn('Failed to dismiss update:', error);
      setVisible(false);
    }
  };

  if (!visible || !updateInfo) return null;

  return (
    <Modal
      transparent
      animationType="fade"
      visible={visible}
      onRequestClose={() => {
        // If force update is active, prevent hardware back button dismissal
        if (!updateInfo.force_update) {
          handleDismiss();
        }
      }}
    >
      <View style={styles.overlay}>
        <View style={styles.modalContainer}>
          {/* Logo Badge Icon */}
          <View style={styles.badgeContainer}>
            <Text style={styles.badgeText}>🚀</Text>
          </View>
          
          <Text style={styles.title}>A New Update Is Here!</Text>
          
          <Text style={styles.message}>
            {updateInfo.message || "A new update is here. Update now to enjoy the latest improvements."}
          </Text>
          
          <Text style={styles.versionLabel}>Version {updateInfo.version}</Text>

          <View style={styles.buttonContainer}>
            {/* Update Now Button */}
            <TouchableOpacity style={styles.updateButton} onPress={handleUpdate} activeOpacity={0.8}>
              <Text style={styles.updateButtonText}>Update Now</Text>
            </TouchableOpacity>

            {/* Later Button (Only if force_update is false) */}
            {!updateInfo.force_update && (
              <TouchableOpacity style={styles.laterButton} onPress={handleDismiss} activeOpacity={0.8}>
                <Text style={styles.laterButtonText}>Later</Text>
              </TouchableOpacity>
            )}
          </View>
        </View>
      </View>
    </Modal>
  );
};

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.75)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: theme.spacing.lg,
  },
  modalContainer: {
    backgroundColor: theme.colors.surfaceDark, // Clean dark modal container
    borderRadius: theme.borderRadius.lg,
    padding: theme.spacing.xl,
    alignItems: 'center',
    width: '100%',
    maxWidth: 320,
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.05)',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.5,
    shadowRadius: 15,
    elevation: 10,
  },
  badgeContainer: {
    width: 64,
    height: 64,
    borderRadius: 32,
    backgroundColor: 'rgba(16, 185, 129, 0.15)', // transparent emerald green circle
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: theme.spacing.md,
    borderWidth: 1,
    borderColor: 'rgba(16, 185, 129, 0.3)',
  },
  badgeText: {
    fontSize: 32,
  },
  title: {
    fontSize: 20,
    fontWeight: '800',
    color: theme.colors.white,
    textAlign: 'center',
    marginBottom: theme.spacing.sm,
  },
  message: {
    fontSize: 14,
    color: theme.colors.textTertiary,
    textAlign: 'center',
    lineHeight: 20,
    marginBottom: theme.spacing.md,
    paddingHorizontal: theme.spacing.xs,
  },
  versionLabel: {
    fontSize: 12,
    fontWeight: '600',
    color: theme.colors.primary,
    backgroundColor: 'rgba(16, 185, 129, 0.1)',
    paddingHorizontal: theme.spacing.sm,
    paddingVertical: theme.spacing.xxs,
    borderRadius: theme.borderRadius.full,
    marginBottom: theme.spacing.xl,
  },
  buttonContainer: {
    width: '100%',
    gap: theme.spacing.sm,
  },
  updateButton: {
    width: '100%',
    backgroundColor: theme.colors.primary, // Fitrova emerald green
    paddingVertical: theme.spacing.md,
    borderRadius: theme.borderRadius.md,
    alignItems: 'center',
    justifyContent: 'center',
  },
  updateButtonText: {
    fontSize: 15,
    fontWeight: '700',
    color: theme.colors.black,
  },
  laterButton: {
    width: '100%',
    backgroundColor: 'rgba(255, 255, 255, 0.05)',
    paddingVertical: theme.spacing.md,
    borderRadius: theme.borderRadius.md,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: 'rgba(255, 255, 255, 0.08)',
  },
  laterButtonText: {
    fontSize: 14,
    fontWeight: '600',
    color: theme.colors.textSecondary,
  },
});
