import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  StyleSheet,
  FlatList,
  TouchableOpacity,
  ActivityIndicator,
  SafeAreaView,
  RefreshControl,
  Linking,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useRoute, useNavigation, RouteProp, useFocusEffect } from '@react-navigation/native';
import { theme } from '../../../theme';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { notificationService, Notification } from '../../../services/api/notificationService';
import { AICoachModal } from '../../../components/common/AICoachModal';
import { RootStackParamList } from '../../../navigation/types';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import Constants from 'expo-constants';
import { getAppUpdate } from '../../../services/api/systemService';
import { CustomAlert } from '../../../components/common/CustomAlert';

type NotificationsRouteProp = RouteProp<RootStackParamList, 'Notifications'>;
type NavigationProp = NativeStackNavigationProp<RootStackParamList>;

export const NotificationsScreen = () => {
  const route = useRoute<NotificationsRouteProp>();
  const navigation = useNavigation<NavigationProp>();
  const userId = route.params?.userId || 1;

  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [notifications, setNotifications] = useState<Notification[]>([]);
  const [activeFilter, setActiveFilter] = useState<'all' | 'warning' | 'tip' | 'achieve_motiv'>('all');
  const [selectedNotification, setSelectedNotification] = useState<Notification | null>(null);
  const [modalVisible, setModalVisible] = useState(false);
  const [darkTheme, setDarkTheme] = useState(false);

  const [appUpdateVersion, setAppUpdateVersion] = useState<string>('');
  const [appUpdateUrl, setAppUpdateUrl] = useState<string>('');
  const [appUpdateMessage, setAppUpdateMessage] = useState<string>('');

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

  const colors = {
    background:    darkTheme ? '#0F172A' : theme.colors.background,
    cardBg:        darkTheme ? '#1E293B' : theme.colors.surface,
    text:          darkTheme ? '#F8FAFC' : theme.colors.text,
    textSecondary: darkTheme ? '#94A3B8' : theme.colors.textSecondary,
    border:        darkTheme ? '#334155' : '#F3F4F6',
    filterBg:      darkTheme ? '#1E293B' : '#F3F4F6',
    emptyCircle:   darkTheme ? '#1E293B' : '#F3F4F6',
  };

  useFocusEffect(
    React.useCallback(() => {
      (async () => {
        try {
          const saved = await AsyncStorage.getItem(`user_prefs_${userId}`);
          if (saved) {
            const prefs = JSON.parse(saved);
            if (prefs.darkTheme !== undefined) setDarkTheme(prefs.darkTheme);
          }
        } catch (e) {}
      })();
    }, [userId])
  );

  const fetchNotifications = useCallback(async (showIndicator = true) => {
    if (showIndicator) setLoading(true);
    try {
      const data = await notificationService.getNotifications(userId);
      let list = [...data.notifications];

      // Fetch latest app update info
      try {
        const updateData = await getAppUpdate();
        const currentVersion = Constants.expoConfig?.version || '1.0.0';
        if (updateData && updateData.is_active && isNewerVersion(currentVersion, updateData.version)) {
          setAppUpdateVersion(updateData.version);
          setAppUpdateUrl(updateData.update_url || '');
          setAppUpdateMessage(updateData.message || '');

          const readStatus = await AsyncStorage.getItem(`read_update_version_${updateData.version}`);
          const isRead = readStatus === 'true';

          const updateNotification: Notification = {
            id: -999,
            insight_text: updateData.message || `A new version of the app (${updateData.version}) is available. Tap here to update now!`,
            insight_type: 'warning',
            is_read: isRead,
            created_at: new Date().toISOString()
          };
          list.unshift(updateNotification);
        }
      } catch (err) {
        console.warn('Failed to fetch/compare app update in notifications screen:', err);
      }

      setNotifications(list);
    } catch (error) {
      console.error('Error fetching notifications:', error);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [userId]);

  useEffect(() => {
    fetchNotifications();
  }, [fetchNotifications]);

  const handleRefresh = () => {
    setRefreshing(true);
    fetchNotifications(false);
  };

  const handleMarkAllRead = async () => {
    try {
      await notificationService.markAsRead(userId);
      if (appUpdateVersion) {
        await AsyncStorage.setItem(`read_update_version_${appUpdateVersion}`, 'true');
      }
      // Update local state instantly
      setNotifications((prev) => prev.map((n) => ({ ...n, is_read: true })));
    } catch (error) {
      console.error('Error marking all as read:', error);
    }
  };

  const handleNotificationPress = async (item: Notification) => {
    if (item.id === -999) {
      // Mark as read locally
      await AsyncStorage.setItem(`read_update_version_${appUpdateVersion}`, 'true');
      setNotifications((prev) =>
        prev.map((n) => (n.id === -999 ? { ...n, is_read: true } : n))
      );

      CustomAlert.alert(
        "A New Update Is Here!",
        `Version ${appUpdateVersion} is available.\n\n${appUpdateMessage || 'Enjoy the latest improvements.'}`,
        [
          {
            text: "Update Now",
            onPress: () => {
              const fallbackUrl = 'https://fitrova-backend.onrender.com/download';
              const storeUrl = appUpdateUrl && appUpdateUrl.trim() !== '' ? appUpdateUrl : fallbackUrl;
              Linking.openURL(storeUrl).catch(err => console.error("Couldn't load page", err));
            }
          },
          {
            text: "Later",
            style: "cancel"
          }
        ]
      );
      return;
    }

    setSelectedNotification(item);
    setModalVisible(true);
    if (!item.is_read) {
      // Mark as read in backend
      await notificationService.markAsRead(userId, item.id);
      // Update local state
      setNotifications((prev) =>
        prev.map((n) => (n.id === item.id ? { ...n, is_read: true } : n))
      );
    }
  };

  const filteredNotifications = notifications.filter((n) => {
    if (activeFilter === 'all') return true;
    if (activeFilter === 'warning') return n.insight_type === 'warning';
    if (activeFilter === 'tip') return n.insight_type === 'tip';
    if (activeFilter === 'achieve_motiv') {
      return n.insight_type === 'achievement' || n.insight_type === 'motivation';
    }
    return true;
  });

  const getNotificationStyle = (type: Notification['insight_type']) => {
    switch (type) {
      case 'warning':
        return {
          icon: 'alert-circle',
          iconColor: '#F59E0B',
          bgColor: '#FEF3C7',
          borderColor: '#FDE68A',
        };
      case 'achievement':
        return {
          icon: 'trophy',
          iconColor: '#6366F1',
          bgColor: '#EEF2FF',
          borderColor: '#C7D2FE',
        };
      case 'motivation':
        return {
          icon: 'flame',
          iconColor: '#EC4899',
          bgColor: '#FDF2F8',
          borderColor: '#FBCFE8',
        };
      case 'tip':
      default:
        return {
          icon: 'bulb',
          iconColor: '#10B981',
          bgColor: '#ECFDF5',
          borderColor: '#A7F3D0',
        };
    }
  };

  const renderItem = ({ item }: { item: Notification }) => {
    const styleConfig = getNotificationStyle(item.insight_type);
    const dateFormatted = new Date(item.created_at).toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });

    return (
      <TouchableOpacity
        style={[
          styles.notificationCard,
          { backgroundColor: colors.cardBg },
          !item.is_read && styles.unreadCard,
          { borderColor: styleConfig.borderColor },
        ]}
        onPress={() => handleNotificationPress(item)}
        activeOpacity={0.8}
      >
        <View
          style={[styles.iconContainer, { backgroundColor: styleConfig.bgColor }]}
        >
          <Ionicons name={styleConfig.icon as any} size={22} color={styleConfig.iconColor} />
        </View>

        <View style={styles.cardContent}>
          <View style={styles.cardHeaderRow}>
            <Text style={[styles.typeTag, { color: colors.textSecondary }]}>{item.insight_type.toUpperCase()}</Text>
            <Text style={[styles.dateText, { color: colors.textSecondary }]}>{dateFormatted}</Text>
          </View>
          <Text style={[styles.insightText, { color: colors.text }]} numberOfLines={2}>
            {item.insight_text}
          </Text>
        </View>

        {!item.is_read && <View style={styles.unreadDot} />}
      </TouchableOpacity>
    );
  };

  const hasUnread = notifications.some((n) => !n.is_read);

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: colors.background }]}>
      {/* Header */}
      <View style={styles.header}>
        <TouchableOpacity
          style={[styles.backButton, { backgroundColor: colors.filterBg }]}
          onPress={() => navigation.goBack()}
        >
          <Ionicons name="arrow-back" size={24} color={colors.text} />
        </TouchableOpacity>
        <Text style={[styles.headerTitle, { color: colors.text }]}>AI Advisor Inbox</Text>
        {hasUnread ? (
          <TouchableOpacity
            style={[styles.markAllButton, { backgroundColor: colors.filterBg }]}
            onPress={handleMarkAllRead}
          >
            <Text style={styles.markAllText}>Read All</Text>
          </TouchableOpacity>
        ) : (
          <View style={{ width: 60 }} />
        )}
      </View>

      {/* Filter Tabs */}
      <View style={styles.filtersContainer}>
        <TouchableOpacity
          style={[styles.filterTab, { backgroundColor: colors.filterBg }, activeFilter === 'all' && styles.activeFilterTab]}
          onPress={() => setActiveFilter('all')}
        >
          <Text style={[styles.filterText, { color: colors.textSecondary }, activeFilter === 'all' && styles.activeFilterText]}>All</Text>
        </TouchableOpacity>

        <TouchableOpacity
          style={[styles.filterTab, { backgroundColor: colors.filterBg }, activeFilter === 'warning' && styles.activeFilterTab]}
          onPress={() => setActiveFilter('warning')}
        >
          <Text style={[styles.filterText, { color: colors.textSecondary }, activeFilter === 'warning' && styles.activeFilterText]}>Alerts</Text>
        </TouchableOpacity>

        <TouchableOpacity
          style={[styles.filterTab, { backgroundColor: colors.filterBg }, activeFilter === 'tip' && styles.activeFilterTab]}
          onPress={() => setActiveFilter('tip')}
        >
          <Text style={[styles.filterText, { color: colors.textSecondary }, activeFilter === 'tip' && styles.activeFilterText]}>Tips</Text>
        </TouchableOpacity>

        <TouchableOpacity
          style={[styles.filterTab, { backgroundColor: colors.filterBg }, activeFilter === 'achieve_motiv' && styles.activeFilterTab]}
          onPress={() => setActiveFilter('achieve_motiv')}
        >
          <Text style={[styles.filterText, { color: colors.textSecondary }, activeFilter === 'achieve_motiv' && styles.activeFilterText]}>Coach Highlights</Text>
        </TouchableOpacity>
      </View>

      {/* Main List */}
      {loading ? (
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color={theme.colors.primary} />
          <Text style={[styles.loadingText, { color: colors.textSecondary }]}>Fetching messages...</Text>
        </View>
      ) : filteredNotifications.length === 0 ? (
        <View style={styles.emptyContainer}>
          <View style={[styles.emptyIconCircle, { backgroundColor: colors.emptyCircle }]}>
            <Ionicons name="notifications-off-outline" size={44} color={colors.textSecondary} />
          </View>
          <Text style={[styles.emptyText, { color: colors.text }]}>No notifications here</Text>
          <Text style={[styles.emptySubtext, { color: colors.textSecondary }]}>
            Your AI coach has no active logs in this category. Complete meals, weights, or workouts to trigger insights!
          </Text>
        </View>
      ) : (
        <FlatList
          data={filteredNotifications}
          keyExtractor={(item) => item.id.toString()}
          renderItem={renderItem}
          contentContainerStyle={styles.listContent}
          showsVerticalScrollIndicator={false}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={handleRefresh} colors={[theme.colors.primary]} />
          }
        />
      )}

      {/* Details popup modal */}
      <AICoachModal
        visible={modalVisible}
        notification={selectedNotification}
        onDismiss={() => {
          setModalVisible(false);
          setSelectedNotification(null);
        }}
      />
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: theme.spacing.lg,
    paddingVertical: theme.spacing.md,
    marginTop: theme.spacing.xl,
  },
  backButton: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: '#F3F4F6',
    justifyContent: 'center',
    alignItems: 'center',
  },
  headerTitle: {
    ...theme.typography.h3,
    fontWeight: '800',
    color: theme.colors.text,
  },
  markAllButton: {
    paddingVertical: 8,
    paddingHorizontal: 12,
    borderRadius: 12,
    backgroundColor: '#F3F4F6',
  },
  markAllText: {
    fontSize: 12,
    fontWeight: '700',
    color: theme.colors.primary,
  },
  filtersContainer: {
    flexDirection: 'row',
    paddingHorizontal: theme.spacing.lg,
    marginVertical: theme.spacing.sm,
    gap: 8,
  },
  filterTab: {
    paddingVertical: 8,
    paddingHorizontal: 14,
    borderRadius: 20,
    backgroundColor: '#F3F4F6',
  },
  activeFilterTab: {
    backgroundColor: theme.colors.primary,
  },
  filterText: {
    fontSize: 12,
    fontWeight: '600',
    color: theme.colors.textSecondary,
  },
  activeFilterText: {
    color: '#FFFFFF',
    fontWeight: '700',
  },
  listContent: {
    paddingHorizontal: theme.spacing.lg,
    paddingBottom: 100,
    paddingTop: theme.spacing.xs,
  },
  notificationCard: {
    flexDirection: 'row',
    backgroundColor: theme.colors.surface,
    borderRadius: 20,
    borderWidth: 1,
    padding: 16,
    marginBottom: 12,
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.03,
    shadowRadius: 10,
    elevation: 1,
  },
  unreadCard: {
    backgroundColor: '#FFFFFF',
    shadowOpacity: 0.06,
    shadowRadius: 12,
    elevation: 3,
  },
  iconContainer: {
    width: 44,
    height: 44,
    borderRadius: 16,
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 14,
  },
  cardContent: {
    flex: 1,
    gap: 4,
  },
  cardHeaderRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  typeTag: {
    fontSize: 9,
    fontWeight: '800',
    color: theme.colors.textSecondary,
    letterSpacing: 0.8,
  },
  dateText: {
    fontSize: 10,
    color: theme.colors.textSecondary,
    fontWeight: '500',
  },
  insightText: {
    fontSize: 13,
    color: theme.colors.text,
    lineHeight: 18,
    fontWeight: '600',
  },
  unreadDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: '#10B981',
    marginLeft: 8,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    gap: theme.spacing.md,
  },
  loadingText: {
    fontSize: 14,
    color: theme.colors.textSecondary,
  },
  emptyContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: 40,
    gap: 16,
  },
  emptyIconCircle: {
    width: 90,
    height: 90,
    borderRadius: 45,
    backgroundColor: '#F3F4F6',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 8,
  },
  emptyText: {
    fontSize: 20,
    fontWeight: '800',
    color: theme.colors.text,
  },
  emptySubtext: {
    fontSize: 13,
    color: theme.colors.textSecondary,
    textAlign: 'center',
    lineHeight: 20,
  },
});
