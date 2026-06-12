import React from 'react';
import { View, Text, StyleSheet, Modal, TouchableOpacity, Dimensions } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { theme } from '../../theme';

interface SubscriptionUpgradeModalProps {
  visible: boolean;
  title?: string;
  message?: string;
  pricingOptions?: {
    premium?: string;
    advanced?: string;
  };
  onClose: () => void;
  onUpgrade: () => void;
}

export const SubscriptionUpgradeModal: React.FC<SubscriptionUpgradeModalProps> = ({
  visible,
  title = '✨ Unlock Visual AI Suite',
  message = 'Trial used. Upgrade to Premium or Advanced Premium to unlock unlimited scans and dynamic joint diagnostics!',
  pricingOptions = { premium: '₦1,500/month', advanced: '₦3,000/month' },
  onClose,
  onUpgrade,
}) => {
  // Helper to parse price and potential promotion text (e.g. "(Save 20% on visual AI suite)")
  const formatPriceInfo = (priceString: string) => {
    if (!priceString) return { price: '', discount: '' };

    const index = priceString.indexOf('(');
    if (index !== -1) {
      return {
        price: priceString.substring(0, index).trim(),
        discount: priceString.substring(index).trim(),
      };
    }

    return {
      price: priceString,
      discount: '',
    };
  };

  const premiumInfo = formatPriceInfo(pricingOptions.premium || '₦1,500/mo');
  const advancedInfo = formatPriceInfo(pricingOptions.advanced || '₦3,000/mo');

  return (
    <Modal
      transparent
      visible={visible}
      animationType="fade"
      onRequestClose={onClose}
    >
      <View style={styles.overlay}>
        <View style={styles.modalContainer}>
          <View style={styles.card}>
            {/* Elegant Solid Emerald Top Accent */}
            <View style={[styles.glowHeader, { backgroundColor: '#10B981' }]} />

            {/* Close Button */}
            <TouchableOpacity style={styles.closeBtn} onPress={onClose} activeOpacity={0.7}>
              <Ionicons name="close" size={18} color={theme.colors.gray[400]} />
            </TouchableOpacity>

            {/* Glowing Emerald Icon Badge */}
            <View style={styles.iconWrapper}>
              <View style={[styles.iconBg, { backgroundColor: '#D1FAE5' }]}>
                <Ionicons name="sparkles" size={24} color="#10B981" />
              </View>
            </View>

            {/* Main Content */}
            <View style={styles.content}>
              <Text style={styles.premiumTag}>Premium Feature Locked</Text>
              <Text style={styles.mainTitle}>{title}</Text>
              <Text style={styles.bodyText}>{message}</Text>
            </View>

            {/* Interactive Pricing Cards */}
            <View style={styles.pricingContainer}>
              {/* Premium AI Tier */}
              <View style={styles.pricingCard}>
                <View style={styles.tierHeader}>
                  <Text style={styles.tierTitle}>Premium AI</Text>
                  <View style={styles.priceContainer}>
                    <Text style={styles.tierPrice}>{premiumInfo.price}</Text>
                    {premiumInfo.discount ? (
                      <Text style={styles.discountText}>{premiumInfo.discount}</Text>
                    ) : null}
                  </View>
                </View>
                <Text style={styles.tierDesc}>AI Coach, Unlimited Workouts, Recovery analysis & trends.</Text>
              </View>

              {/* Advanced Premium Tier */}
              <View style={[styles.pricingCard, styles.pricingCardActive]}>
                <View style={[StyleSheet.absoluteFillObject, { backgroundColor: 'rgba(16, 185, 129, 0.04)' }]} />

                <View style={styles.tierHeader}>
                  <View style={styles.titleWithBadge}>
                    <Text style={[styles.tierTitle, { color: '#10B981' }]}>Advanced Premium</Text>
                    <View style={styles.popularBadge}>
                      <Text style={styles.popularText}>BEST VALUE</Text>
                    </View>
                  </View>

                  <View style={styles.priceContainer}>
                    <Text style={styles.tierPrice}>{advancedInfo.price}</Text>
                    {advancedInfo.discount ? (
                      <Text style={styles.discountText}>{advancedInfo.discount}</Text>
                    ) : null}
                  </View>
                </View>
                <Text style={styles.tierDesc}>Visual AI camera scan suite, Biomechanics posture, Diet Coach.</Text>
              </View>
            </View>

            {/* Action Buttons */}
            <View style={styles.actions}>
              <TouchableOpacity
                onPress={onUpgrade}
                activeOpacity={0.9}
                style={[styles.primaryBtn, { backgroundColor: '#10B981' }]}
              >
                <Text style={styles.primaryBtnText}>Upgrade Now</Text>
                <Ionicons name="arrow-forward-outline" size={14} color="#FFFFFF" />
              </TouchableOpacity>

              <TouchableOpacity
                style={styles.secondaryBtn}
                onPress={onClose}
                activeOpacity={0.7}
              >
                <Text style={styles.secondaryBtnText}>Maybe Later</Text>
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
    backgroundColor: 'rgba(15, 23, 42, 0.75)', // Elegant backdrop
    justifyContent: 'center',
    alignItems: 'center',
    padding: 16,
  },
  modalContainer: {
    width: '100%',
    maxWidth: width * 0.90,
    alignItems: 'center',
  },
  card: {
    width: '100%',
    backgroundColor: '#FFFFFF',
    borderRadius: 24,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    overflow: 'hidden',
    padding: 16,
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 8 },
    shadowOpacity: 0.15,
    shadowRadius: 20,
    elevation: 10,
  },
  glowHeader: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    height: 5,
  },
  closeBtn: {
    position: 'absolute',
    top: 12,
    right: 12,
    width: 28,
    height: 28,
    borderRadius: 14,
    backgroundColor: theme.colors.gray[50],
    justifyContent: 'center',
    alignItems: 'center',
    zIndex: 10,
  },
  iconWrapper: {
    width: 60,
    height: 60,
    borderRadius: 30,
    backgroundColor: 'rgba(16, 185, 129, 0.08)',
    justifyContent: 'center',
    alignItems: 'center',
    marginTop: theme.spacing.xs,
    marginBottom: theme.spacing.sm,
  },
  iconBg: {
    width: 48,
    height: 48,
    borderRadius: 24,
    justifyContent: 'center',
    alignItems: 'center',
  },
  content: {
    alignItems: 'center',
    marginBottom: theme.spacing.sm,
  },
  premiumTag: {
    fontSize: 9.5,
    fontWeight: '800',
    color: '#10B981',
    letterSpacing: 1.2,
    textTransform: 'uppercase',
    marginBottom: theme.spacing.xs,
  },
  mainTitle: {
    ...theme.typography.h3,
    fontWeight: '800',
    color: theme.colors.text,
    textAlign: 'center',
    fontSize: 16,
    lineHeight: 20,
    marginBottom: theme.spacing.xs,
  },
  bodyText: {
    ...theme.typography.body,
    fontSize: 11,
    color: theme.colors.textSecondary,
    textAlign: 'center',
    lineHeight: 16,
    paddingHorizontal: theme.spacing.xs,
  },
  pricingContainer: {
    width: '100%',
    gap: theme.spacing.xs,
    marginBottom: theme.spacing.md,
  },
  pricingCard: {
    width: '100%',
    padding: 10,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#F3F4F6',
    backgroundColor: '#F9FAFB',
    overflow: 'hidden',
  },
  pricingCardActive: {
    borderColor: '#10B981',
    backgroundColor: '#FFFFFF',
  },
  popularBadge: {
    backgroundColor: '#10B981',
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: 6,
    marginLeft: 6,
    transform: [{ translateY: -7 }],
  },
  popularText: {
    color: '#FFFFFF',
    fontSize: 7.5,
    fontWeight: '800',
    letterSpacing: 0.5,
  },
  tierHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 4,
    width: '100%',
  },
  titleWithBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    flexShrink: 1,
  },
  priceContainer: {
    alignItems: 'flex-end',
    justifyContent: 'center',
    marginLeft: 8,
  },
  discountText: {
    fontSize: 8.0,
    color: '#059669', // Emerald green discount label
    fontWeight: '600',
    marginTop: 1,
  },
  tierTitle: {
    fontSize: 12.5,
    fontWeight: '700',
    color: theme.colors.text,
  },
  tierPrice: {
    fontSize: 11,
    fontWeight: '800',
    color: theme.colors.text,
  },
  tierDesc: {
    fontSize: 9.5,
    color: theme.colors.textSecondary,
    lineHeight: 13,
  },
  actions: {
    width: '100%',
    gap: theme.spacing.xs,
  },
  primaryBtn: {
    height: 44,
    borderRadius: 12,
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
    gap: 6,
    width: '100%',
  },
  primaryBtnText: {
    color: '#FFFFFF',
    fontSize: 14,
    fontWeight: '700',
    letterSpacing: 0.5,
  },
  secondaryBtn: {
    height: 38,
    justifyContent: 'center',
    alignItems: 'center',
    width: '100%',
  },
  secondaryBtnText: {
    color: theme.colors.textSecondary,
    fontSize: 12,
    fontWeight: '600',
  },
});
