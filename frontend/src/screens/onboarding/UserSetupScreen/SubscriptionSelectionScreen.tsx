import React, { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  ActivityIndicator,
  Linking,
  Modal,
  Alert
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useNavigation, useRoute, RouteProp } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { RootStackParamList } from '../../../navigation/types';
import { ProgressHeader } from '../../../components/common/ProgressHeader';
import { CustomAlert } from '../../../components/common/CustomAlert';
import { Button } from '../../../components/buttons/Button';
import { Ionicons } from '@expo/vector-icons';
import { theme } from '../../../theme';
import { endpoints } from '../../../services/api/apiClient';
import { WebView } from 'react-native-webview';

type NavigationProp = NativeStackNavigationProp<RootStackParamList, 'SubscriptionSelection'>;
type RouteProps = RouteProp<RootStackParamList, 'SubscriptionSelection'>;

export const SubscriptionSelectionScreen = () => {
  const navigation = useNavigation<NavigationProp>();
  const route = useRoute<RouteProps>();
  const { userId, firstName } = route.params;

  const [selectedTier, setSelectedTier] = useState<'free' | 'premium' | 'advanced_premium'>('premium');
  const [isLoading, setIsLoading] = useState(false);
  const [checkoutUrl, setCheckoutUrl] = useState<string | null>(null);
  const [paymentReference, setPaymentReference] = useState<string | null>(null);
  const [isVerifying, setIsVerifying] = useState(false);
  // Gate: is the payment system currently enabled by admin?
  const [paymentsEnabled, setPaymentsEnabled] = useState<boolean | null>(null); // null = loading

  React.useEffect(() => {
    (async () => {
      try {
        const res = await fetch(endpoints.getSystemStatus);
        const data = await res.json();
        setPaymentsEnabled(data?.data?.payments_enabled !== false);

        if (data?.data?.monetization_enabled === false) {
          // Silently set user profile to Complete and free tier
          await fetch(endpoints.saveProfile, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              userId: userId,
              survey_step: 'Complete',
              subscription_tier: 'free'
            }),
          });

          // Save Complete status to AsyncStorage local session
          const savedSession = await AsyncStorage.getItem('user_session');
          const session = savedSession ? JSON.parse(savedSession) : { id: userId, firstName };
          session.surveyStep = 'Complete';
          if (!session.profile) session.profile = {};
          session.profile.subscriptionTier = 'free';
          await AsyncStorage.setItem('user_session', JSON.stringify(session));

          navigation.navigate('Main', { firstName, userId });
        }
      } catch (e) {
        setPaymentsEnabled(true); // fail open
        console.error(e);
      }
    })();
  }, [userId, firstName]);

  const pricingTiers = [
    {
      id: 'free' as const,
      title: '🍃 Free Trial (Eco Smart)',
      price: '₦0.00',
      period: 'forever',
      color: '#10B981',
      bgSelected: '#F0FDF4',
      borderSelected: '#10B981',
      description: 'Get exactly ONE free AI workout generation. Following standard sessions run locally on 0 tokens. AI diets and cameras are locked.',
      features: [
        '1 Free AI workout generation session',
        'Offline-capable local rules workouts',
        'Curated manual logs (No food scanner)',
        'Standard category exercise videos'
      ]
    },
    {
      id: 'premium' as const,
      title: '✨ Premium Tier (AI Coach)',
      price: '₦1,500',
      period: 'month',
      color: '#F59E0B',
      bgSelected: '#FEF3C7',
      borderSelected: '#F59E0B',
      description: 'Unlock daily personalized AI workout strategy modifications from our Gemini Pro trainer + live YouTube tutorials.',
      features: [
        'Unlimited Gemini AI workout plans',
        'Live YouTube workout tutorial search',
        'Dynamic high-res video thumbnails',
        'Dynamic daily recovery score insights',
        'Save plans to database schedule'
      ]
    },
    {
      id: 'advanced_premium' as const,
      title: '🚀 Advanced Premium (All-Access)',
      price: '₦3,000',
      period: 'month',
      color: '#8B5CF6',
      bgSelected: '#F5F3FF',
      borderSelected: '#8B5CF6',
      description: 'Access the complete visual AI coaching suite. Perfect for athletes wanting posture form cameras and food photo scanner diagnostics.',
      features: [
        'Everything in Premium Tier',
        '🥗 AI Nutrition Coach diet plans',
        '📸 AI Meal Scanner camera calorie estimates',
        '🎥 AI Biomechanics joint alignment posture checker',
        'Advanced health and trend diagnostics'
      ]
    }
  ];

  const handleStartFreeTrial = async () => {
    setIsLoading(true);
    try {
      const payload = {
        userId: userId,
        survey_step: 'Complete',
        subscription_tier: 'free',
        subscription_expiry: null
      };

      const response = await fetch(endpoints.saveProfile, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });

      const result = await response.json();

      if (response.ok && result.status === 'success') {
        // Save Complete status to AsyncStorage local session
        const savedSession = await AsyncStorage.getItem('user_session');
        const session = savedSession ? JSON.parse(savedSession) : { id: userId, firstName };
        session.surveyStep = 'Complete';
        if (!session.profile) session.profile = {};
        session.profile.subscriptionTier = 'free';
        await AsyncStorage.setItem('user_session', JSON.stringify(session));

        CustomAlert.alert('Welcome!', 'Your 1-Workout Free Trial is active. Let\'s build your strategy!');
        navigation.navigate('Main', { firstName, userId });
      } else {
        CustomAlert.alert('Activation Failed', result.message || 'Failed to start free trial');
      }
    } catch (e) {
      CustomAlert.alert('Network Error', 'Failed to connect to the server.');
      console.error(e);
    } finally {
      setIsLoading(false);
    }
  };

  const handleInitializePaystack = async () => {
    // ── Payment Gate Check ───────────────────────────────────────────
    // Re-validate gate state from server right before launching checkout
    let gateOpen = paymentsEnabled;
    try {
      const gateRes  = await fetch(endpoints.getSystemStatus);
      const gateData = await gateRes.json();
      gateOpen = gateData?.data?.payments_enabled !== false;
      setPaymentsEnabled(gateOpen);
    } catch { /* fail open */ }

    if (!gateOpen) {
      CustomAlert.alert(
        'Payments Temporarily Unavailable',
        'Our payment system is undergoing maintenance. Please try again shortly — your free trial is always available in the meantime.'
      );
      return;
    }
    // ────────────────────────────────────────────────────────────────

    setIsLoading(true);
    try {
      // Connect to the backend initializer
      const response = await fetch(endpoints.paystackInitialize, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          user_id: userId,
          subscription_tier: selectedTier
        })
      });

      const result = await response.json();

      if (response.ok && result.status === 'success') {
        const authUrl = result.data.authorization_url;
        const ref = result.data.reference;

        setPaymentReference(ref);
        setCheckoutUrl(authUrl); // Open checkout directly in our secure in-app WebView modal
      } else {
        CustomAlert.alert('Billing Error', result.message || 'Could not initialize Paystack transaction');
      }
    } catch (e) {
      CustomAlert.alert('Connection Failed', 'Could not reach the payment server.');
      console.error(e);
    } finally {
      setIsLoading(false);
    }
  };

  const handleVerifyPayment = async (overrideRef?: string) => {
    const ref = overrideRef || paymentReference;
    if (!ref) return;
    setIsVerifying(true);

    try {
      // Call backend verifier to verify transaction reference with Paystack
      const response = await fetch(endpoints.paystackVerify, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ reference: ref })
      });

      const result = await response.json();

      if (response.ok && result.status === 'success') {
        // Successfully verified! Save session status
        const savedSession = await AsyncStorage.getItem('user_session');
        const session = savedSession ? JSON.parse(savedSession) : { id: userId, firstName };
        session.surveyStep = 'Complete';
        if (!session.profile) session.profile = {};
        session.profile.subscriptionTier = selectedTier;
        await AsyncStorage.setItem('user_session', JSON.stringify(session));

        // Call profile update to set surveyStep to Complete in database
        await fetch(endpoints.saveProfile, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            userId: userId,
            survey_step: 'Complete',
            subscription_tier: selectedTier
          }),
        });

        setCheckoutUrl(null);
        CustomAlert.alert('Subscription Unlocked!', `Congratulations, your account has been successfully upgraded to ${selectedTier === 'premium' ? 'Premium AI Coach' : 'Advanced Premium All-Access'}!`);
        navigation.navigate('Main', { firstName, userId });
      } else {
        if (!overrideRef) {
          CustomAlert.alert('Verification Pending', 'We haven\'t received payment confirmation yet. Please ensure you finished the payment inside the opened browser window.');
        }
      }
    } catch (e) {
      if (!overrideRef) {
        CustomAlert.alert('Verification Failed', 'Failed to communicate with payment verification servers.');
      }
      console.error(e);
    } finally {
      setIsVerifying(false);
    }
  };

  const handleProceed = () => {
    if (selectedTier === 'free') {
      handleStartFreeTrial();
    } else {
      handleInitializePaystack();
    }
  };

  return (
    <SafeAreaView style={styles.container}>
      <View style={{ paddingHorizontal: 20, paddingTop: 10 }}>
        <ProgressHeader
          currentStep={4}
          totalSteps={4}
          stepLabel="100%"
          onBackPress={() => navigation.goBack()}
        />
      </View>

      <ScrollView contentContainerStyle={styles.scrollContent} showsVerticalScrollIndicator={false}>
        <Text style={styles.title}>Unlock Fitrova</Text>
        <Text style={styles.subtitle}>Choose your plan. Denominated securely in Naira (₦).</Text>

        {pricingTiers.map((tier) => (
          <TouchableOpacity
            key={tier.id}
            style={[
              styles.tierCard,
              selectedTier === tier.id && {
                borderColor: tier.borderSelected,
                backgroundColor: tier.bgSelected
              }
            ]}
            onPress={() => setSelectedTier(tier.id)}
            activeOpacity={0.8}
          >
            <View style={styles.tierHeader}>
              <Text style={[styles.tierTitle, { color: tier.color }]}>{tier.title}</Text>
              <View style={styles.priceRow}>
                <Text style={styles.priceText}>{tier.price}</Text>
                <Text style={styles.periodText}>/ {tier.period}</Text>
              </View>
            </View>

            <Text style={styles.tierDescription}>{tier.description}</Text>

            <View style={styles.divider} />

            <View style={styles.featuresContainer}>
              {tier.features.map((feature, idx) => (
                <View key={idx} style={styles.featureRow}>
                  <Ionicons name="checkmark-circle" size={18} color={tier.color} />
                  <Text style={styles.featureText}>{feature}</Text>
                </View>
              ))}
            </View>
          </TouchableOpacity>
        ))}

        <Button
          title={
            isLoading ? 'Loading Payment...' :
            selectedTier === 'free' ? 'Start Free Trial' :
            paymentsEnabled === false ? 'Payments Unavailable' :
            'Subscribe via Paystack'
          }
          onPress={handleProceed}
          disabled={isLoading || (selectedTier !== 'free' && paymentsEnabled === false)}
          style={styles.actionButton}
          icon={<Ionicons name="arrow-forward" size={18} color="#FFFFFF" />}
        />
        {paymentsEnabled === false && selectedTier !== 'free' && (
          <Text style={styles.paymentDisabledNote}>
            ⚠️ Payments are temporarily paused by the administrator. You can still start your free trial.
          </Text>
        )}

        <View style={styles.bottomSpacer} />
      </ScrollView>

      {/* Secure In-App WebView Checkout Modal */}
      <Modal visible={checkoutUrl !== null} transparent={false} animationType="slide">
        <SafeAreaView style={{ flex: 1, backgroundColor: '#FFFFFF' }}>
          {/* WebView Header */}
          <View style={styles.webHeader}>
            <TouchableOpacity 
              onPress={() => {
                Alert.alert(
                  "Cancel Checkout?",
                  "Are you sure you want to exit the checkout? If you have already charged your card, please let the app finish verifying in the background.",
                  [
                    { text: "Keep Paying", style: "default" },
                    { text: "Exit", style: "destructive", onPress: () => setCheckoutUrl(null) }
                  ]
                );
              }} 
              style={styles.webCloseBtn}
            >
              <Ionicons name="arrow-back" size={24} color={theme.colors.text} />
              <Text style={styles.webCloseText}>Exit Checkout</Text>
            </TouchableOpacity>
            
            <Text style={styles.webHeaderTitle}>Paystack Checkout</Text>
            
            {isVerifying ? (
              <ActivityIndicator size="small" color="#10B981" />
            ) : (
              <View style={{ width: 24 }} />
            )}
          </View>
          
          {checkoutUrl ? (
            <WebView
              source={{ uri: checkoutUrl }}
              onNavigationStateChange={(navState) => {
                console.log('WebView URL Transition:', navState.url);
                // Detect redirect callback
                if (
                  navState.url.includes('paystack_callback.php') || 
                  navState.url.includes('example.com/payment-success') || 
                  navState.url.includes('reference=')
                ) {
                  const match = navState.url.match(/[?&]reference=([^&]+)/);
                  const ref = match ? match[1] : paymentReference;
                  
                  if (ref && !isVerifying) {
                    console.log('Automated redirection verified for ref:', ref);
                    handleVerifyPayment(ref);
                  }
                }
              }}
              style={{ flex: 1 }}
              startInLoadingState={true}
              renderLoading={() => (
                <View style={styles.webLoader}>
                  <ActivityIndicator size="large" color="#10B981" />
                  <Text style={styles.webLoaderText}>Securing secure gateway connection...</Text>
                </View>
              )}
            />
          ) : null}
        </SafeAreaView>
      </Modal>
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  scrollContent: {
    paddingHorizontal: 20,
    paddingTop: 30,
  },
  title: {
    fontSize: 28,
    fontWeight: 'bold',
    color: theme.colors.text,
    marginBottom: 8,
  },
  subtitle: {
    fontSize: 15,
    color: '#6B7280',
    marginBottom: 24,
  },
  tierCard: {
    backgroundColor: '#FFFFFF',
    borderRadius: 16,
    borderWidth: 1.5,
    borderColor: '#E5E7EB',
    padding: 14,
    marginBottom: 12,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.04,
    shadowRadius: 6,
    elevation: 1,
  },
  tierHeader: {
    marginBottom: 8,
    gap: 4,
  },
  tierTitle: {
    fontSize: 14,
    fontWeight: '700',
  },
  priceRow: {
    flexDirection: 'row',
    alignItems: 'baseline',
  },
  priceText: {
    fontSize: 19,
    fontWeight: '800',
    color: theme.colors.text,
  },
  periodText: {
    fontSize: 11,
    color: '#6B7280',
    marginLeft: 4,
    fontWeight: '600',
  },
  tierDescription: {
    fontSize: 11,
    color: '#4B5563',
    lineHeight: 15,
    marginBottom: 10,
  },
  divider: {
    height: 1,
    backgroundColor: '#E5E7EB',
    marginBottom: 10,
  },
  featuresContainer: {
    gap: 6,
  },
  featureRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  featureText: {
    fontSize: 11,
    color: '#374151',
    fontWeight: '500',
    flex: 1,
  },
  actionButton: {
    marginTop: 10,
    height: 48,
  },
  bottomSpacer: {
    height: 40,
  },
  paymentDisabledNote: {
    marginTop: 10,
    fontSize: 12,
    color: '#EF4444',
    fontWeight: '600',
    textAlign: 'center',
    lineHeight: 18,
    paddingHorizontal: 8,
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.6)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 24,
  },
  modalContainer: {
    width: '100%',
    backgroundColor: '#FFFFFF',
    borderRadius: 24,
    padding: 24,
    alignItems: 'center',
  },
  modalIcon: {
    marginBottom: 16,
  },
  modalTitle: {
    fontSize: 20,
    fontWeight: 'bold',
    color: theme.colors.text,
    marginBottom: 8,
  },
  modalMsg: {
    fontSize: 14,
    color: '#4B5563',
    textAlign: 'center',
    lineHeight: 20,
    marginBottom: 24,
  },
  modalConfirmBtn: {
    width: '100%',
    height: 48,
    marginBottom: 12,
  },
  modalCancelBtn: {
    paddingVertical: 10,
  },
  modalCancelText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#EF4444',
  },
  webHeader: {
    height: 56,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    borderBottomWidth: 1,
    borderBottomColor: '#E5E7EB',
    backgroundColor: '#FFFFFF',
    paddingHorizontal: 16,
  },
  webCloseBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  webCloseText: {
    fontSize: 14,
    fontWeight: '600',
    color: theme.colors.text,
  },
  webHeaderTitle: {
    fontSize: 15,
    fontWeight: '700',
    color: theme.colors.text,
  },
  webLoader: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: '#FFFFFF',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 24,
  },
  webLoaderText: {
    marginTop: 16,
    fontSize: 14,
    color: '#6B7280',
    fontWeight: '500',
    textAlign: 'center',
  },
});
