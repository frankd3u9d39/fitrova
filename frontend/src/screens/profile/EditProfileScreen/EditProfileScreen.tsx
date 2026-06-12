import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  KeyboardAvoidingView,
  Platform,
  ActivityIndicator,
  Image
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { useNavigation, useRoute, RouteProp } from '@react-navigation/native';
import * as ImagePicker from 'expo-image-picker';
import { RootStackParamList } from '../../../navigation/types';
import { Input } from '../../../components/inputs/Input';
import { Button } from '../../../components/buttons/Button';
import { theme } from '../../../theme';
import { getProfileStats, updateProfile } from '../../../services/api/profileService';
import { CustomAlert } from '../../../components/common/CustomAlert';
import AsyncStorage from '@react-native-async-storage/async-storage';

type EditProfileRouteProp = RouteProp<RootStackParamList, 'EditProfile'>;

export const EditProfileScreen = () => {
  const navigation = useNavigation();
  const route = useRoute<EditProfileRouteProp>();
  const userId = route.params?.userId || 1;

  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [email, setEmail] = useState('');
  const [motto, setMotto] = useState('');
  const [profilePicture, setProfilePicture] = useState<string | null>(null);

  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [darkTheme, setDarkTheme] = useState(false);

  useEffect(() => {
    loadCurrentProfile();
    (async () => {
      try {
        const saved = await AsyncStorage.getItem(`user_prefs_${userId}`);
        if (saved) {
          const prefs = JSON.parse(saved);
          if (prefs.darkTheme !== undefined) setDarkTheme(prefs.darkTheme);
        }
      } catch (e) { }
    })();
  }, [userId]);

  const loadCurrentProfile = async () => {
    try {
      setLoading(true);
      const profileData = await getProfileStats(userId);
      setFirstName(profileData.user.first_name);
      setLastName(profileData.user.last_name);
      setEmail(profileData.user.email);
      setMotto(profileData.user.motto || '');
      setProfilePicture(profileData.user.profile_picture || null);
    } catch (err) {
      console.error('Error loading profile for edit:', err);
      CustomAlert.alert('Error', 'Unable to retrieve current profile details');
    } finally {
      setLoading(false);
    }
  };

  const pickImage = async () => {
    try {
      // Request permission
      const permissionResult = await ImagePicker.requestMediaLibraryPermissionsAsync();

      if (!permissionResult.granted) {
        CustomAlert.alert(
          'Permission Required',
          'Please allow access to your photo library to set a profile picture.'
        );
        return;
      }

      // Launch image picker
      const result = await ImagePicker.launchImageLibraryAsync({
        mediaTypes: ['images'],
        allowsEditing: true,
        aspect: [1, 1],
        quality: 0.5,
        base64: true,
      });

      if (!result.canceled && result.assets[0]) {
        const asset = result.assets[0];
        if (asset.base64) {
          // Build a data URI from the base64 string
          const mimeType = asset.mimeType || 'image/jpeg';
          const dataUri = `data:${mimeType};base64,${asset.base64}`;
          setProfilePicture(dataUri);
        }
      }
    } catch (err) {
      console.error('Error picking image:', err);
      CustomAlert.alert('Error', 'Failed to pick an image. Please try again.');
    }
  };

  const handleSave = async () => {
    if (!firstName.trim() || !lastName.trim() || !email.trim()) {
      CustomAlert.alert('Error', 'First name, last name, and email are required');
      return;
    }

    try {
      setSaving(true);
      await updateProfile({
        user_id: userId,
        first_name: firstName,
        last_name: lastName,
        email: email,
        motto: motto,
        profile_picture: profilePicture,
      });

      CustomAlert.alert('Success', 'Profile updated successfully!', [
        {
          text: 'OK',
          onPress: () => navigation.goBack()
        }
      ]);
    } catch (err: any) {
      console.error('Error saving profile:', err);
      CustomAlert.alert('Failed to Save', err.message || 'Server error occurred while saving profile');
    } finally {
      setSaving(false);
    }
  };

  // Dynamic theme colors matching SettingsScreen
  const colors = {
    background: darkTheme ? '#0F172A' : '#F3F4F6',
    cardBg: darkTheme ? '#1E293B' : '#FFFFFF',
    text: darkTheme ? '#F8FAFC' : '#1F2937',
    textSecondary: darkTheme ? '#94A3B8' : '#6B7280',
    border: darkTheme ? '#334155' : '#F3F4F6',
    inputBg: darkTheme ? '#0F172A' : '#F9FAFB',
    inputBorder: darkTheme ? '#334155' : '#E5E7EB',
  };

  if (loading) {
    return (
      <SafeAreaView style={[styles.container, { backgroundColor: colors.background }]}>
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#10B981" />
          <Text style={[styles.loadingText, { color: colors.textSecondary }]}>Fetching profile details...</Text>
        </View>
      </SafeAreaView>
    );
  }

  const initials = ((firstName?.[0] || '') + (lastName?.[0] || '')).toUpperCase() || 'U';

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: colors.background }]}>
      {/* Header */}
      <View style={styles.header}>
        <TouchableOpacity style={styles.backButton} onPress={() => navigation.goBack()}>
          <Ionicons name="arrow-back" size={24} color={colors.text} />
        </TouchableOpacity>
        <Text style={[styles.headerTitle, { color: colors.text }]}>Edit Profile</Text>
        <View style={{ width: 40 }} />
      </View>

      <KeyboardAvoidingView
        style={{ flex: 1 }}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      >
        <ScrollView
          contentContainerStyle={styles.scrollContent}
          showsVerticalScrollIndicator={false}
          keyboardShouldPersistTaps="handled"
        >
          {/* Avatar with Image Picker */}
          <View style={styles.avatarSection}>
            <TouchableOpacity
              style={styles.avatarContainer}
              onPress={pickImage}
              activeOpacity={0.7}
            >
              <View style={styles.avatar}>
                {profilePicture ? (
                  <Image
                    source={{ uri: profilePicture }}
                    style={styles.avatarImage}
                  />
                ) : (
                  <Text style={styles.avatarText}>{initials}</Text>
                )}
              </View>
              <View style={[styles.cameraIconBox, { borderColor: colors.background }]}>
                <Ionicons name="camera" size={16} color="#FFFFFF" />
              </View>
            </TouchableOpacity>
            <Text style={styles.avatarSubtext}>Tap to change photo</Text>
          </View>

          {/* Form Card */}
          <View style={[styles.formCard, { backgroundColor: colors.cardBg, borderColor: colors.border }]}>
            <Input
              label="First Name"
              placeholder="First name"
              value={firstName}
              onChangeText={setFirstName}
              autoCapitalize="words"
              style={{ backgroundColor: colors.inputBg, borderColor: colors.inputBorder }}
              inputStyle={{ color: colors.text }}
              labelStyle={{ color: colors.textSecondary }}
              placeholderTextColor={colors.textSecondary}
            />

            <Input
              label="Last Name"
              placeholder="Last name"
              value={lastName}
              onChangeText={setLastName}
              autoCapitalize="words"
              style={{ backgroundColor: colors.inputBg, borderColor: colors.inputBorder }}
              inputStyle={{ color: colors.text }}
              labelStyle={{ color: colors.textSecondary }}
              placeholderTextColor={colors.textSecondary}
            />

            <Input
              label="Email Address"
              placeholder="email@example.com"
              value={email}
              onChangeText={setEmail}
              keyboardType="email-address"
              autoCapitalize="none"
              style={{ backgroundColor: colors.inputBg, borderColor: colors.inputBorder }}
              inputStyle={{ color: colors.text }}
              labelStyle={{ color: colors.textSecondary }}
              placeholderTextColor={colors.textSecondary}
            />

            <Input
              label="Personal Motto"
              placeholder="e.g. Striving for 1% better every day"
              value={motto}
              onChangeText={setMotto}
              autoCapitalize="sentences"
              style={{ backgroundColor: colors.inputBg, borderColor: colors.inputBorder }}
              inputStyle={{ color: colors.text }}
              labelStyle={{ color: colors.textSecondary }}
              placeholderTextColor={colors.textSecondary}
            />
          </View>

          {/* Save Button */}
          <Button
            title={saving ? 'Saving changes...' : 'Save Changes'}
            onPress={handleSave}
            disabled={saving}
            style={styles.saveButton}
          />
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#F9FAFB',
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    gap: 16,
  },
  loadingText: {
    fontSize: 14,
    color: '#6B7280',
    fontWeight: '500',
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 20,
    paddingVertical: 16,
  },
  backButton: {
    width: 40,
    height: 40,
    justifyContent: 'center',
  },
  headerTitle: {
    fontSize: 20,
    fontWeight: '800',
    color: '#1F2937',
    letterSpacing: 0.5,
  },
  scrollContent: {
    paddingHorizontal: 20,
    paddingBottom: 40,
  },
  avatarSection: {
    alignItems: 'center',
    marginVertical: 24,
  },
  avatarContainer: {
    position: 'relative',
    marginBottom: 12,
  },
  avatar: {
    width: 110,
    height: 110,
    borderRadius: 55,
    backgroundColor: '#ECFDF5',
    justifyContent: 'center',
    alignItems: 'center',
    borderWidth: 4,
    borderColor: '#10B981',
    shadowColor: '#10B981',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.1,
    shadowRadius: 10,
    elevation: 3,
    overflow: 'hidden',
  },
  avatarImage: {
    width: '100%',
    height: '100%',
    borderRadius: 55,
  },
  avatarText: {
    fontSize: 32,
    fontWeight: '800',
    color: '#10B981',
    letterSpacing: 0.5,
  },
  cameraIconBox: {
    position: 'absolute',
    bottom: 0,
    right: 0,
    width: 32,
    height: 32,
    borderRadius: 16,
    backgroundColor: '#10B981',
    justifyContent: 'center',
    alignItems: 'center',
    borderWidth: 3,
    borderColor: '#F9FAFB',
  },
  avatarSubtext: {
    fontSize: 12,
    fontWeight: '600',
    color: '#9CA3AF',
  },
  formCard: {
    backgroundColor: '#FFFFFF',
    borderRadius: 24,
    padding: 24,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.03,
    shadowRadius: 8,
    elevation: 2,
    borderWidth: 1,
    borderColor: '#F3F4F6',
    gap: 8,
    marginBottom: 28,
  },
  saveButton: {
    shadowColor: '#10B981',
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.2,
    shadowRadius: 12,
    elevation: 4,
  },
});
