import React from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  Switch,
  Platform,
  Alert
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { useNavigation } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { RootStackParamList } from '../../../navigation/types';

type NavigationProp = NativeStackNavigationProp<RootStackParamList>;

export const SettingsScreen = () => {
  const navigation = useNavigation<NavigationProp>();
  const [pushEnabled, setPushEnabled] = React.useState(true);
  const [darkTheme, setDarkTheme] = React.useState(false);

  const handleLogout = () => {
    Alert.alert(
      "Log Out",
      "Are you sure you want to log out of your Fitrova account?",
      [
        { text: "Cancel", style: "cancel" },
        { 
          text: "Log Out", 
          style: "destructive",
          onPress: () => {
            // Navigate back to the initial Welcome screen, clearing the stack
            navigation.reset({
              index: 0,
              routes: [{ name: 'Welcome' }],
            });
          }
        }
      ]
    );
  };

  const renderSectionHeader = (title: string) => (
    <Text style={styles.sectionTitle}>{title}</Text>
  );

  const renderSettingRow = (
    icon: keyof typeof Ionicons.glyphMap,
    title: string,
    onPress?: () => void,
    control?: React.ReactNode,
    isDestructive?: boolean
  ) => (
    <TouchableOpacity 
      style={styles.settingRow} 
      onPress={onPress} 
      disabled={!onPress}
      activeOpacity={0.7}
    >
      <View style={[styles.iconBox, isDestructive && styles.iconBoxDestructive]}>
        <Ionicons name={icon} size={20} color={isDestructive ? "#EF4444" : "#10B981"} />
      </View>
      <Text style={[styles.settingTitle, isDestructive && styles.settingTitleDestructive]}>
        {title}
      </Text>
      <View style={styles.controlContainer}>
        {control ? control : <Ionicons name="chevron-forward" size={20} color="#9CA3AF" />}
      </View>
    </TouchableOpacity>
  );

  return (
    <SafeAreaView style={styles.container}>
      {/* Header */}
      <View style={styles.header}>
        <TouchableOpacity style={styles.backButton} onPress={() => navigation.goBack()}>
          <Ionicons name="arrow-back" size={24} color="#1F2937" />
        </TouchableOpacity>
        <Text style={styles.headerTitle}>Settings</Text>
        <View style={{ width: 40 }} />
      </View>

      <ScrollView contentContainerStyle={styles.scrollContent} showsVerticalScrollIndicator={false}>
        
        {/* Account Section */}
        {renderSectionHeader('ACCOUNT')}
        <View style={styles.card}>
          {renderSettingRow('person-outline', 'Edit Profile', () => {})}
          <View style={styles.separator} />
          {renderSettingRow('lock-closed-outline', 'Security & Password', () => {})}
          <View style={styles.separator} />
          {renderSettingRow('shield-checkmark-outline', 'Privacy Preferences', () => {})}
        </View>

        {/* Preferences Section */}
        {renderSectionHeader('PREFERENCES')}
        <View style={styles.card}>
          {renderSettingRow(
            'notifications-outline', 
            'Push Notifications', 
            undefined, 
            <Switch 
              value={pushEnabled} 
              onValueChange={setPushEnabled}
              trackColor={{ false: '#E2E8F0', true: '#34D399' }}
              thumbColor={Platform.OS === 'ios' ? '#FFFFFF' : pushEnabled ? '#10B981' : '#F8FAFC'}
            />
          )}
          <View style={styles.separator} />
          {renderSettingRow(
            'moon-outline', 
            'Dark Mode', 
            undefined, 
            <Switch 
              value={darkTheme} 
              onValueChange={setDarkTheme}
              trackColor={{ false: '#E2E8F0', true: '#34D399' }}
              thumbColor={Platform.OS === 'ios' ? '#FFFFFF' : darkTheme ? '#10B981' : '#F8FAFC'}
            />
          )}
          <View style={styles.separator} />
          {renderSettingRow('language-outline', 'Language', () => {}, <Text style={styles.valueText}>English</Text>)}
        </View>

        {/* Support Section */}
        {renderSectionHeader('SUPPORT')}
        <View style={styles.card}>
          {renderSettingRow('help-buoy-outline', 'Help Center', () => {})}
          <View style={styles.separator} />
          {renderSettingRow('bug-outline', 'Report a Bug', () => {})}
          <View style={styles.separator} />
          {renderSettingRow('document-text-outline', 'Terms of Service', () => {})}
        </View>

        {/* App Info / Logout */}
        <View style={[styles.card, styles.logoutCard]}>
          {renderSettingRow('log-out-outline', 'Log Out', handleLogout, <View />, true)}
        </View>
        <Text style={styles.versionText}>Fitrova v1.2.0 (Build 42)</Text>

        <View style={{ height: 40 }} />
      </ScrollView>
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#F3F4F6', // slightly darker off-white for contrast against white cards
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 20,
    paddingVertical: 16,
    backgroundColor: '#F3F4F6',
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
    paddingTop: 10,
    paddingBottom: 40,
  },
  sectionTitle: {
    fontSize: 13,
    fontWeight: '800',
    color: '#6B7280',
    letterSpacing: 1.2,
    marginBottom: 8,
    marginTop: 24,
    marginLeft: 12,
  },
  card: {
    backgroundColor: '#FFFFFF',
    borderRadius: 20,
    overflow: 'hidden',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.03,
    shadowRadius: 8,
    elevation: 2,
    borderWidth: 1,
    borderColor: '#F3F4F6',
  },
  settingRow: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 16,
    backgroundColor: '#FFFFFF',
  },
  iconBox: {
    width: 36,
    height: 36,
    borderRadius: 10,
    backgroundColor: '#ECFDF5',
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 16,
  },
  iconBoxDestructive: {
    backgroundColor: '#FEF2F2',
  },
  settingTitle: {
    flex: 1,
    fontSize: 16,
    fontWeight: '600',
    color: '#1F2937',
  },
  settingTitleDestructive: {
    color: '#EF4444',
  },
  controlContainer: {
    justifyContent: 'center',
    alignItems: 'flex-end',
  },
  separator: {
    height: 1,
    backgroundColor: '#F3F4F6',
    marginLeft: 68, // Aligns exactly with the text start
  },
  valueText: {
    fontSize: 15,
    color: '#6B7280',
    fontWeight: '500',
  },
  logoutCard: {
    marginTop: 32,
    marginBottom: 16,
  },
  versionText: {
    textAlign: 'center',
    fontSize: 12,
    color: '#9CA3AF',
    fontWeight: '600',
    letterSpacing: 0.5,
  }
});
