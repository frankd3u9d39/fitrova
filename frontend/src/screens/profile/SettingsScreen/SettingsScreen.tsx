import React, { useState, useEffect, useRef } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  Switch,
  Platform,
  Alert,
  Modal,
  Linking,
  Animated,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { useNavigation, useRoute, RouteProp } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { RootStackParamList } from '../../../navigation/types';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { savePreferences } from '../../../services/api/settingsService';
import { CustomAlert } from '../../../components/common/CustomAlert';

type NavigationProp = NativeStackNavigationProp<RootStackParamList>;

const FAQ_ITEMS = [
  { q: 'How does AI Form Check work?', a: 'The AI Form Check uses your camera to record a 5-second video of your movement. Our AI analyzes your joint alignment and posture, then gives you a form score and corrections.' },
  { q: 'Can I use Fitrova without equipment?', a: 'Yes! Fitrova generates bodyweight workout plans for users without equipment. Update your profile to set "No Equipment" and your plans will adapt accordingly.' },
  { q: 'How is my calorie goal calculated?', a: 'Your daily calorie goal is based on your age, gender, height, weight, and activity level using the Mifflin-St Jeor formula, adjusted for your fitness goal.' },
  { q: 'How do I unlock achievements?', a: 'Achievements unlock automatically when you complete workouts. For example, "First Step" unlocks after your first workout, and "7-Day Streak" requires 7 consecutive days of working out.' },
  { q: 'Is my data safe?', a: 'Yes. Your data is stored securely on our servers. We never share your personal information with third parties. You can request data deletion at any time by contacting support.' },
];

export const SettingsScreen = () => {
  const navigation = useNavigation<NavigationProp>();
  const route = useRoute<RouteProp<RootStackParamList, 'Settings'>>();
  const userId = route.params?.userId || 1;

  // ── Preferences state ─────────────────────────────
  const [pushEnabled, setPushEnabled] = useState(true);
  const [darkTheme, setDarkTheme] = useState(false);
  const [units, setUnits] = useState<'metric' | 'imperial'>('metric');

  // ── Modal visibility ──────────────────────────────
  const [showHelpModal, setShowHelpModal] = useState(false);
  const [showUnitsModal, setShowUnitsModal] = useState(false);
  const [showTermsModal, setShowTermsModal] = useState(false);
  const [showPrivacyModal, setShowPrivacyModal] = useState(false);

  // ── FAQ accordion ─────────────────────────────────
  const [openFaq, setOpenFaq] = useState<number | null>(null);

  // Dynamic theme colors
  const colors = {
    background: darkTheme ? '#0F172A' : '#F3F4F6',
    cardBg: darkTheme ? '#1E293B' : '#FFFFFF',
    text: darkTheme ? '#F8FAFC' : '#1F2937',
    textSecondary: darkTheme ? '#94A3B8' : '#6B7280',
    border: darkTheme ? '#334155' : '#F3F4F6',
    separator: darkTheme ? '#334155' : '#F3F4F6',
    inputBg: darkTheme ? '#0F172A' : '#F9FAFB',
    inputBorder: darkTheme ? '#334155' : '#E5E7EB',
  };

  // Load saved preferences from AsyncStorage on mount
  useEffect(() => {
    (async () => {
      try {
        const saved = await AsyncStorage.getItem(`user_prefs_${userId}`);
        if (saved) {
          const prefs = JSON.parse(saved);
          if (prefs.pushEnabled !== undefined) setPushEnabled(prefs.pushEnabled);
          if (prefs.darkTheme !== undefined) setDarkTheme(prefs.darkTheme);
          if (prefs.units) setUnits(prefs.units);
        }
      } catch (e) { }
    })();
  }, [userId]);

  const persistPrefs = async (patch: object) => {
    try {
      const saved = await AsyncStorage.getItem(`user_prefs_${userId}`);
      const current = saved ? JSON.parse(saved) : {};
      await AsyncStorage.setItem(`user_prefs_${userId}`, JSON.stringify({ ...current, ...patch }));
    } catch (e) { }
  };

  const handleTogglePush = async (val: boolean) => {
    setPushEnabled(val);
    persistPrefs({ pushEnabled: val });
    try { await savePreferences(userId, { notification_enabled: val }); } catch (_) { }
  };

  const handleToggleDark = async (val: boolean) => {
    setDarkTheme(val);
    persistPrefs({ darkTheme: val });
  };

  const handleSelectUnits = async (val: 'metric' | 'imperial') => {
    setUnits(val);
    setShowUnitsModal(false);
    persistPrefs({ units: val });
    try { await savePreferences(userId, { unit_preference: val }); } catch (_) { }
  };

  const handleLogout = () => {
    Alert.alert(
      'Log Out',
      'Are you sure you want to log out of your Fitrova account?',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Log Out',
          style: 'destructive',
          onPress: async () => {
            try { await AsyncStorage.removeItem('user_session'); } catch (e) { }
            navigation.reset({ index: 0, routes: [{ name: 'Welcome' }] });
          },
        },
      ]
    );
  };

  // ── Render helpers ────────────────────────────────
  const renderSection = (title: string) => (
    <Text style={[styles.sectionTitle, { color: colors.textSecondary }]}>{title}</Text>
  );

  const renderRow = (
    icon: keyof typeof Ionicons.glyphMap,
    title: string,
    onPress?: () => void,
    control?: React.ReactNode,
    isDestructive?: boolean
  ) => (
    <TouchableOpacity
      style={[styles.settingRow, { backgroundColor: colors.cardBg }]}
      onPress={onPress}
      disabled={!onPress}
      activeOpacity={0.7}
    >
      <View style={[
        styles.iconBox,
        isDestructive ? styles.iconBoxDestructive : (darkTheme && { backgroundColor: '#064E3B' })
      ]}>
        <Ionicons name={icon} size={20} color={isDestructive ? '#EF4444' : '#34D399'} />
      </View>
      <Text style={[
        styles.settingTitle,
        { color: isDestructive ? '#EF4444' : colors.text }
      ]}>
        {title}
      </Text>
      <View style={styles.controlContainer}>
        {control !== undefined ? control : <Ionicons name="chevron-forward" size={20} color={colors.textSecondary} />}
      </View>
    </TouchableOpacity>
  );

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: colors.background }]}>
      <View style={[styles.header, { backgroundColor: colors.background }]}>
        <TouchableOpacity style={styles.backButton} onPress={() => navigation.goBack()}>
          <Ionicons name="arrow-back" size={24} color={colors.text} />
        </TouchableOpacity>
        <Text style={[styles.headerTitle, { color: colors.text }]}>Settings</Text>
        <View style={{ width: 40 }} />
      </View>

      <ScrollView contentContainerStyle={styles.scrollContent} showsVerticalScrollIndicator={false}>

        {/* ACCOUNT */}
        {renderSection('ACCOUNT')}
        <View style={[styles.card, { backgroundColor: colors.cardBg, borderColor: colors.border }]}>
          {renderRow('person-outline', 'Edit Profile', () => navigation.navigate('EditProfile', { userId }))}
          <View style={[styles.separator, { backgroundColor: colors.separator }]} />
          {renderRow('shield-checkmark-outline', 'Unit Preferences', () => setShowUnitsModal(true), (
            <Text style={[styles.valueText, { color: colors.textSecondary }]}>{units === 'metric' ? 'kg / cm' : 'lbs / ft'}</Text>
          ))}
        </View>

        {/* PREFERENCES */}
        {renderSection('PREFERENCES')}
        <View style={[styles.card, { backgroundColor: colors.cardBg, borderColor: colors.border }]}>
          {renderRow(
            'notifications-outline',
            'Push Notifications',
            undefined,
            <Switch
              value={pushEnabled}
              onValueChange={handleTogglePush}
              trackColor={{ false: darkTheme ? '#334155' : '#E2E8F0', true: '#34D399' }}
              thumbColor={Platform.OS === 'ios' ? '#FFFFFF' : pushEnabled ? '#10B981' : '#F8FAFC'}
            />
          )}
          <View style={[styles.separator, { backgroundColor: colors.separator }]} />
          {renderRow(
            'moon-outline',
            'Dark Mode',
            undefined,
            <Switch
              value={darkTheme}
              onValueChange={handleToggleDark}
              trackColor={{ false: darkTheme ? '#334155' : '#E2E8F0', true: '#34D399' }}
              thumbColor={Platform.OS === 'ios' ? '#FFFFFF' : darkTheme ? '#10B981' : '#F8FAFC'}
            />
          )}
        </View>

        {/* SUPPORT */}
        {renderSection('SUPPORT')}
        <View style={[styles.card, { backgroundColor: colors.cardBg, borderColor: colors.border }]}>
          {renderRow('help-buoy-outline', 'Help Center', () => setShowHelpModal(true))}
          <View style={[styles.separator, { backgroundColor: colors.separator }]} />
          {renderRow('bug-outline', 'Report a Bug', () =>
            Linking.openURL('mailto:ibehpromise30@gmail.com?subject=Bug%20Report&body=Describe%20the%20bug%20here...')
          )}
          <View style={[styles.separator, { backgroundColor: colors.separator }]} />
          {renderRow('document-text-outline', 'Terms of Service', () => setShowTermsModal(true))}
          <View style={[styles.separator, { backgroundColor: colors.separator }]} />
          {renderRow('information-circle-outline', 'Privacy Policy', () => setShowPrivacyModal(true))}
        </View>

        {/* LOGOUT */}
        <View style={[styles.card, styles.logoutCard, { backgroundColor: colors.cardBg, borderColor: colors.border }]}>
          {renderRow('log-out-outline', 'Log Out', handleLogout, <View />, true)}
        </View>
        <Text style={[styles.versionText, { color: colors.textSecondary }]}>Fitrova v1.2.0 · Build 42</Text>
        <View style={{ height: 40 }} />
      </ScrollView>



      {/* ── Units Modal ──────────────────────────────── */}
      <Modal visible={showUnitsModal} animationType="slide" presentationStyle="pageSheet" onRequestClose={() => setShowUnitsModal(false)}>
        <SafeAreaView style={[styles.modalContainer, { backgroundColor: colors.cardBg }]}>
          <View style={[styles.modalHeader, { borderBottomColor: colors.border }]}>
            <Text style={[styles.modalTitle, { color: colors.text }]}>Unit Preferences</Text>
            <TouchableOpacity onPress={() => setShowUnitsModal(false)}>
              <Ionicons name="close" size={26} color={colors.textSecondary} />
            </TouchableOpacity>
          </View>

          <View style={[styles.modalContent, { backgroundColor: colors.cardBg }]}>
            <Text style={[styles.modalSubtext, { color: colors.textSecondary }]}>Choose how weight and height are displayed throughout the app.</Text>
            {[
              { val: 'metric' as const, label: 'Metric', detail: 'Kilograms (kg) · Centimetres (cm)', icon: '🌍' },
              { val: 'imperial' as const, label: 'Imperial', detail: 'Pounds (lbs) · Feet & Inches (ft)', icon: '🌎' },
            ].map(opt => (
              <TouchableOpacity
                key={opt.val}
                style={[styles.optionRow, { backgroundColor: darkTheme ? '#1E293B' : '#FAFAFA', borderColor: colors.inputBorder }, units === opt.val && styles.optionRowActive]}
                onPress={() => handleSelectUnits(opt.val)}
              >
                <Text style={styles.optionFlag}>{opt.icon}</Text>
                <View style={{ flex: 1 }}>
                  <Text style={[styles.optionLabel, { color: colors.text }, units === opt.val && styles.optionLabelActive]}>{opt.label}</Text>
                  <Text style={[styles.optionDetail, { color: colors.textSecondary }]}>{opt.detail}</Text>
                </View>
                {units === opt.val && <Ionicons name="checkmark-circle" size={22} color="#10B981" />}
              </TouchableOpacity>
            ))}
          </View>
        </SafeAreaView>
      </Modal>


      {/* ── Help Center / FAQ Modal ──────────────────── */}
      <Modal visible={showHelpModal} animationType="slide" presentationStyle="pageSheet" onRequestClose={() => setShowHelpModal(false)}>
        <SafeAreaView style={[styles.modalContainer, { backgroundColor: colors.cardBg }]}>
          <View style={[styles.modalHeader, { borderBottomColor: colors.border }]}>
            <Text style={[styles.modalTitle, { color: colors.text }]}>Help Center</Text>
            <TouchableOpacity onPress={() => setShowHelpModal(false)}>
              <Ionicons name="close" size={26} color={colors.textSecondary} />
            </TouchableOpacity>
          </View>

          <ScrollView contentContainerStyle={styles.modalContent} style={{ backgroundColor: colors.cardBg }}>
            <Text style={[styles.modalSubtext, { color: colors.textSecondary }]}>Frequently asked questions about Fitrova.</Text>

            {FAQ_ITEMS.map((item, i) => (
              <TouchableOpacity
                key={i}
                style={[styles.faqItem, { backgroundColor: darkTheme ? '#1E293B' : '#F9FAFB', borderColor: colors.inputBorder }]}
                onPress={() => setOpenFaq(openFaq === i ? null : i)}
                activeOpacity={0.8}
              >
                <View style={styles.faqQuestion}>
                  <Text style={[styles.faqQuestionText, { color: colors.text }]}>{item.q}</Text>
                  <Ionicons
                    name={openFaq === i ? 'chevron-up' : 'chevron-down'}
                    size={18}
                    color={colors.textSecondary}
                  />
                </View>
                {openFaq === i && (
                  <Text style={[styles.faqAnswer, { color: colors.textSecondary }]}>{item.a}</Text>
                )}
              </TouchableOpacity>
            ))}

            <View style={[styles.contactBox, { backgroundColor: darkTheme ? '#064E3B' : '#F0FDF4', borderColor: darkTheme ? '#065F46' : '#D1FAE5' }]}>
              <Ionicons name="mail-outline" size={24} color="#10B981" />
              <View style={{ flex: 1 }}>
                <Text style={[styles.contactTitle, { color: colors.text }]}>Still need help?</Text>
                <Text style={[styles.contactSub, { color: colors.textSecondary }]}>Our team is here to help you.</Text>
              </View>
              <TouchableOpacity
                style={styles.contactBtn}
                onPress={() => Linking.openURL('mailto:ibehpromise30@gmail.com')}
              >
                <Text style={styles.contactBtnText}>Email Us</Text>
              </TouchableOpacity>
            </View>
          </ScrollView>
        </SafeAreaView>
      </Modal>

      {/* ── Terms of Service Modal ──────────────────── */}
      <Modal visible={showTermsModal} animationType="slide" presentationStyle="pageSheet" onRequestClose={() => setShowTermsModal(false)}>
        <SafeAreaView style={[styles.modalContainer, { backgroundColor: colors.cardBg }]}>
          <View style={[styles.modalHeader, { borderBottomColor: colors.border }]}>
            <Text style={[styles.modalTitle, { color: colors.text }]}>Terms of Service</Text>
            <TouchableOpacity onPress={() => setShowTermsModal(false)}>
              <Ionicons name="close" size={26} color={colors.textSecondary} />
            </TouchableOpacity>
          </View>

          <ScrollView contentContainerStyle={styles.termsModalContent} style={{ backgroundColor: colors.cardBg }}>
            <Text style={[styles.termsDate, { color: colors.textSecondary }]}>Last updated: June 12, 2026</Text>
            <Text style={[styles.termsBody, { color: colors.text }]}>
              Welcome to Fitrova! Please read these Terms of Service ("Terms") carefully before using the Fitrova mobile application and related services operated by us.
            </Text>

            <Text style={[styles.termsHeading, { color: colors.text }]}>1. Acceptance of Terms</Text>
            <Text style={[styles.termsBody, { color: colors.textSecondary }]}>
              By creating an account, logging in, or using the Fitrova app, you agree to be bound by these Terms. If you do not agree to all of the terms, you must not use or access the services.
            </Text>

            <Text style={[styles.termsHeading, { color: colors.text }]}>2. Eligibility and Accounts</Text>
            <Text style={[styles.termsBody, { color: colors.textSecondary }]}>
              You must be at least 13 years old to use Fitrova. You are responsible for safeguarding the credentials you use to access the service and for any activities or actions under your account.
            </Text>

            <Text style={[styles.termsHeading, { color: colors.text }]}>3. AI Coaching & Medical Disclaimer</Text>
            <Text style={[styles.termsBody, { color: darkTheme ? '#F59E0B' : '#D97706', fontWeight: '600' }]}>
              ⚠️ Fitrova provides AI-powered workout recommendations, fitness suggestions, and computer-vision based form analysis. All suggestions, plans, and form ratings are for informational, motivational, and educational purposes only.
            </Text>
            <Text style={[styles.termsBody, { color: colors.textSecondary }]}>
              Fitrova is not a medical organization or physical therapy clinic. The content and features provided do not constitute medical advice, diagnosis, or treatment. Always consult a qualified physician or professional healthcare provider before starting any physical fitness or diet regimen. You assume all risk and liability for any injuries or damages resulting from physical activities guided by our AI models.
            </Text>

            <Text style={[styles.termsHeading, { color: colors.text }]}>4. Subscription and Billing</Text>
            <Text style={[styles.termsBody, { color: colors.textSecondary }]}>
              Some features of Fitrova require paid subscriptions. Subscription fees are billed in advance on a recurring, periodic basis. You can cancel your subscription at any time through your account settings or application store preferences.
            </Text>

            <Text style={[styles.termsHeading, { color: colors.text }]}>5. User Content and Behavior</Text>
            <Text style={[styles.termsBody, { color: colors.textSecondary }]}>
              You agree not to upload files containing viruses, malicious code, or materials that violate intellectual property rights. We reserve the right to suspend or terminate accounts that breach these standards or misuse our AI endpoints.
            </Text>

            <Text style={[styles.termsHeading, { color: colors.text }]}>6. Limitation of Liability</Text>
            <Text style={[styles.termsBody, { color: colors.textSecondary }]}>
              To the maximum extent permitted by law, Fitrova and its developers shall not be liable for any indirect, incidental, special, consequential, or punitive damages, including loss of profits, data, or personal injury resulting from your use of the app.
            </Text>

            <Text style={[styles.termsHeading, { color: colors.text }]}>7. Contact Us</Text>
            <Text style={[styles.termsBody, { color: colors.textSecondary }]}>
              If you have any questions regarding these Terms, please contact our support team at ibehpromise30@gmail.com.
            </Text>

            <View style={{ height: 40 }} />
          </ScrollView>
        </SafeAreaView>
      </Modal>

      {/* ── Privacy Policy Modal ────────────────────── */}
      <Modal visible={showPrivacyModal} animationType="slide" presentationStyle="pageSheet" onRequestClose={() => setShowPrivacyModal(false)}>
        <SafeAreaView style={[styles.modalContainer, { backgroundColor: colors.cardBg }]}>
          <View style={[styles.modalHeader, { borderBottomColor: colors.border }]}>
            <Text style={[styles.modalTitle, { color: colors.text }]}>Privacy Policy</Text>
            <TouchableOpacity onPress={() => setShowPrivacyModal(false)}>
              <Ionicons name="close" size={26} color={colors.textSecondary} />
            </TouchableOpacity>
          </View>

          <ScrollView contentContainerStyle={styles.termsModalContent} style={{ backgroundColor: colors.cardBg }}>
            <Text style={[styles.termsDate, { color: colors.textSecondary }]}>Last updated: June 12, 2026</Text>
            <Text style={[styles.termsBody, { color: colors.text }]}>
              At Fitrova, we value your trust. This Privacy Policy describes how we collect, use, and protect your personal information when you use our mobile application and backend services.
            </Text>

            <Text style={[styles.termsHeading, { color: colors.text }]}>1. Information We Collect</Text>
            <Text style={[styles.termsBody, { color: colors.textSecondary }]}>
              We collect information to deliver personalized AI-powered fitness services. This includes:
            </Text>
            <Text style={[styles.termsBullet, { color: colors.textSecondary }]}>• Account credentials (email, username, and secure password hashes).</Text>
            <Text style={[styles.termsBullet, { color: colors.textSecondary }]}>• Physical profile stats (age, gender, height, weight, fitness goals, and equipment preferences).</Text>
            <Text style={[styles.termsBullet, { color: colors.textSecondary }]}>• Workout activity (exercise logs, achievements, streaks, and generated plans).</Text>
            <Text style={[styles.termsBullet, { color: colors.textSecondary }]}>• Camera recordings (short video clips you upload for AI joint and posture form check analysis).</Text>

            <Text style={[styles.termsHeading, { color: colors.text }]}>2. Video and Form Analysis Processing</Text>
            <Text style={[styles.termsBody, { color: colors.textSecondary }]}>
              When you use the AI Form Check feature, the application uploads a video to our secure AI Form Analyzer endpoint. Joint coordinates and posture alignment are evaluated programmatically. These video files are only processed to return form coach analysis and are not retained persistently on our servers or shared with any advertising networks.
            </Text>

            <Text style={[styles.termsHeading, { color: colors.text }]}>3. How We Use Information</Text>
            <Text style={[styles.termsBody, { color: colors.textSecondary }]}>
              We use your data to generate customized daily workout recommendations, track fitness achievements, send push notifications, and monitor fallback AI capabilities (such as Gemma 2 and local coach logic) to optimize system performance.
            </Text>

            <Text style={[styles.termsHeading, { color: colors.text }]}>4. Data Security & Storage</Text>
            <Text style={[styles.termsBody, { color: colors.textSecondary }]}>
              We use industry-standard encryption, SSL protocols, and secure cloud databases (including Render containers and Aiven DB) to safeguard your data. While we implement rigorous controls, no transmission method over the Internet is 100% secure.
            </Text>

            <Text style={[styles.termsHeading, { color: colors.text }]}>5. Third-Party Services</Text>
            <Text style={[styles.termsBody, { color: colors.textSecondary }]}>
              We may utilize secure third-party AI models (such as Google Gemini and Hugging Face Inference API space services) to generate structured fitness plans. These third parties receive anonymized profile parameters and do not have access to your personal contact details.
            </Text>

            <Text style={[styles.termsHeading, { color: colors.text }]}>6. Account Deletion and Rights</Text>
            <Text style={[styles.termsBody, { color: colors.textSecondary }]}>
              You can access, modify, or update your profile statistics directly from the App settings. To request full deletion of your account and personal history, contact us at privacy@fitrova.app.
            </Text>

            <Text style={[styles.termsHeading, { color: colors.text }]}>7. Contact Us</Text>
            <Text style={[styles.termsBody, { color: colors.textSecondary }]}>
              If you have any questions or feedback about our privacy practices, please contact us at ibehpromise30@gmail.com.
            </Text>

            <View style={{ height: 40 }} />
          </ScrollView>
        </SafeAreaView>
      </Modal>
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#F3F4F6',
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
    marginLeft: 68,
  },
  valueText: {
    fontSize: 14,
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
  },

  // ── Modals ────────────────────────────────────────
  modalContainer: {
    flex: 1,
    backgroundColor: '#FFFFFF',
  },
  modalHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 24,
    paddingVertical: 18,
    borderBottomWidth: 1,
    borderBottomColor: '#F3F4F6',
  },
  modalTitle: {
    fontSize: 20,
    fontWeight: '800',
    color: '#1F2937',
  },
  modalContent: {
    padding: 24,
    gap: 16,
  },
  modalSubtext: {
    fontSize: 14,
    color: '#6B7280',
    lineHeight: 20,
    marginBottom: 8,
  },

  // Password inputs
  inputLabel: {
    fontSize: 13,
    fontWeight: '700',
    color: '#374151',
    marginBottom: 6,
    marginTop: 4,
  },
  passwordRow: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#F9FAFB',
    borderRadius: 14,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    paddingHorizontal: 16,
    height: 52,
    marginBottom: 12,
  },
  passwordInput: {
    flex: 1,
    fontSize: 15,
    color: '#1F2937',
  },
  eyeBtn: {
    padding: 4,
  },
  saveBtn: {
    backgroundColor: '#10B981',
    height: 52,
    borderRadius: 14,
    justifyContent: 'center',
    alignItems: 'center',
    marginTop: 8,
  },
  saveBtnText: {
    color: '#FFFFFF',
    fontSize: 15,
    fontWeight: '700',
  },

  // Options (units/language)
  optionRow: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 16,
    borderRadius: 14,
    borderWidth: 1.5,
    borderColor: '#E5E7EB',
    gap: 14,
    backgroundColor: '#FAFAFA',
  },
  optionRowActive: {
    borderColor: '#10B981',
    backgroundColor: '#F0FDF4',
  },
  optionFlag: {
    fontSize: 24,
  },
  optionLabel: {
    fontSize: 16,
    fontWeight: '600',
    color: '#1F2937',
  },
  optionLabelActive: {
    color: '#10B981',
  },
  optionDetail: {
    fontSize: 12,
    color: '#9CA3AF',
    marginTop: 2,
  },

  // FAQ
  faqItem: {
    backgroundColor: '#F9FAFB',
    borderRadius: 14,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    padding: 16,
    gap: 10,
  },
  faqQuestion: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 10,
  },
  faqQuestionText: {
    flex: 1,
    fontSize: 14,
    fontWeight: '700',
    color: '#1F2937',
  },
  faqAnswer: {
    fontSize: 14,
    color: '#6B7280',
    lineHeight: 20,
  },

  // Contact box
  contactBox: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 14,
    backgroundColor: '#F0FDF4',
    borderRadius: 16,
    padding: 16,
    borderWidth: 1,
    borderColor: '#D1FAE5',
    marginTop: 8,
  },
  contactTitle: {
    fontSize: 14,
    fontWeight: '700',
    color: '#1F2937',
  },
  contactSub: {
    fontSize: 12,
    color: '#6B7280',
  },
  contactBtn: {
    backgroundColor: '#10B981',
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 10,
  },
  contactBtnText: {
    color: '#FFFFFF',
    fontSize: 13,
    fontWeight: '700',
  },
  // Terms & Privacy Modals
  termsModalContent: {
    padding: 24,
  },
  termsDate: {
    fontSize: 13,
    fontWeight: '600',
    marginBottom: 16,
  },
  termsHeading: {
    fontSize: 16,
    fontWeight: '700',
    marginTop: 20,
    marginBottom: 8,
  },
  termsBody: {
    fontSize: 14,
    lineHeight: 22,
    marginBottom: 12,
  },
  termsBullet: {
    fontSize: 14,
    lineHeight: 22,
    marginLeft: 16,
    marginBottom: 6,
  },
});
