import React, { useState, useEffect, useRef } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  TextInput,
  FlatList,
  Image,
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
  Alert,
  Keyboard,
  Animated,
  PanResponder
} from 'react-native';
import * as Haptics from 'expo-haptics';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { theme } from '../../theme';
import { useRoute, useNavigation, RouteProp } from '@react-navigation/native';
import { RootStackParamList } from '../../navigation/types';
import AsyncStorage from '@react-native-async-storage/async-storage';
import {
  getChallengeDetails,
  getChallengeMessages,
  sendChallengeMessage,
  handleConnection,
  ChatMessage,
  ChallengeCommunityDetails
} from '../../services/api/communityService';

type ChallengeCommunityRouteProp = RouteProp<RootStackParamList, 'ChallengeCommunity'>;

interface SwipeableMessageRowProps {
  item: ChatMessage;
  isMe: boolean;
  onReply: (message: ChatMessage) => void;
  children: React.ReactNode;
}

const SwipeableMessageRow: React.FC<SwipeableMessageRowProps> = ({ item, isMe, onReply, children }) => {
  const dragX = useRef(new Animated.Value(0)).current;
  const hasTriggeredHaptic = useRef(false);

  const panResponder = useRef(
    PanResponder.create({
      onStartShouldSetPanResponder: () => false,
      onMoveShouldSetPanResponder: (evt, gestureState) => {
        // Activate responder for horizontal swipe to the right
        return Math.abs(gestureState.dx) > 10 && gestureState.dx > 0 && Math.abs(gestureState.dy) < 15;
      },
      onPanResponderMove: (evt, gestureState) => {
        const val = Math.min(gestureState.dx, 80);
        dragX.setValue(val);
        
        if (val > 50 && !hasTriggeredHaptic.current) {
          Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light).catch(() => {});
          hasTriggeredHaptic.current = true;
        } else if (val <= 50 && hasTriggeredHaptic.current) {
          hasTriggeredHaptic.current = false;
        }
      },
      onPanResponderRelease: (evt, gestureState) => {
        if (gestureState.dx > 50) {
          onReply(item);
        }
        hasTriggeredHaptic.current = false;
        Animated.spring(dragX, {
          toValue: 0,
          useNativeDriver: true,
          tension: 40,
          friction: 7
        }).start();
      },
      onPanResponderTerminate: () => {
        hasTriggeredHaptic.current = false;
        Animated.spring(dragX, {
          toValue: 0,
          useNativeDriver: true
        }).start();
      }
    })
  ).current;

  return (
    <View style={styles.swipeContainer}>
      {/* Reply Icon Background Layer */}
      <Animated.View
        style={[
          styles.replyIconContainer,
          {
            opacity: dragX.interpolate({
              inputRange: [0, 50],
              outputRange: [0, 1],
              extrapolate: 'clamp'
            }),
            transform: [
              {
                translateX: dragX.interpolate({
                  inputRange: [0, 50],
                  outputRange: [-20, 0],
                  extrapolate: 'clamp'
                })
              }
            ]
          }
        ]}
      >
        <Ionicons name="arrow-undo" size={20} color="#10B981" />
      </Animated.View>

      {/* Animatable Row Content */}
      <Animated.View
        style={{
          flex: 1,
          transform: [{ translateX: dragX }]
        }}
        {...panResponder.panHandlers}
      >
        {children}
      </Animated.View>
    </View>
  );
};

export const ChallengeCommunityScreen = () => {
  const route = useRoute<ChallengeCommunityRouteProp>();
  const navigation = useNavigation();
  const { challengeKey, challengeTitle, userId } = route.params;

  const [loading, setLoading] = useState(true);
  const [details, setDetails] = useState<ChallengeCommunityDetails | null>(null);
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const [newMessage, setNewMessage] = useState('');
  const [sendingMessage, setSendingMessage] = useState(false);
  const [darkTheme, setDarkTheme] = useState(false);
  const [replyToMessage, setReplyToMessage] = useState<ChatMessage | null>(null);

  const chatListRef = useRef<FlatList>(null);

  // Dynamic theme colors matching the app
  const colors = {
    background: darkTheme ? '#0F172A' : '#F9FAFB',
    cardBg: darkTheme ? '#1E293B' : '#FFFFFF',
    text: darkTheme ? '#F8FAFC' : '#1F2937',
    textSecondary: darkTheme ? '#94A3B8' : '#6B7280',
    border: darkTheme ? '#334155' : '#E5E7EB',
    chatBubbleMe: '#10B981',
    chatBubbleOther: darkTheme ? '#334155' : '#F3F4F6',
    chatTextOther: darkTheme ? '#F8FAFC' : '#1F2937',
    chatTime: darkTheme ? '#64748B' : '#9CA3AF',
    inputBg: darkTheme ? '#1E293B' : '#FFFFFF',
  };

  useEffect(() => {
    // Load dark mode preference
    (async () => {
      try {
        const saved = await AsyncStorage.getItem(`user_prefs_${userId}`);
        if (saved) {
          const prefs = JSON.parse(saved);
          if (prefs.darkTheme !== undefined) setDarkTheme(prefs.darkTheme);
        }
      } catch (e) { }
    })();

    loadInitialData();
  }, [userId, challengeKey]);

  // Message polling effect
  useEffect(() => {
    let intervalId: NodeJS.Timeout;
    if (details) {
      intervalId = setInterval(async () => {
        try {
          const fetchedMessages = await getChallengeMessages(challengeKey);
          setMessages(fetchedMessages);
        } catch (e) {
          console.error('Failed to poll chat messages', e);
        }
      }, 4000); // Poll every 4 seconds
    }
    return () => {
      if (intervalId) clearInterval(intervalId);
    };
  }, [details]);

  const loadInitialData = async () => {
    try {
      setLoading(true);
      const data = await getChallengeDetails(userId, challengeKey);
      setDetails(data);
      const chatMessages = await getChallengeMessages(challengeKey);
      setMessages(chatMessages);
    } catch (e: any) {
      Alert.alert('Error', e.message || 'Failed to load community data');
    } finally {
      loadInitialData;
      setLoading(false);
    }
  };

  const handleRefresh = async () => {
    try {
      const data = await getChallengeDetails(userId, challengeKey);
      setDetails(data);
      const chatMessages = await getChallengeMessages(challengeKey);
      setMessages(chatMessages);
    } catch (e: any) {
      console.error('Failed to refresh data', e);
    }
  };

  const handleSendMessage = async () => {
    if (newMessage.trim() === '') return;
    const msgText = newMessage.trim();
    const replyId = replyToMessage?.id;
    setNewMessage('');
    setReplyToMessage(null);
    setSendingMessage(true);
    Keyboard.dismiss();

    try {
      await sendChallengeMessage(userId, challengeKey, msgText, replyId);
      const chatMessages = await getChallengeMessages(challengeKey);
      setMessages(chatMessages);
      setTimeout(() => chatListRef.current?.scrollToEnd({ animated: true }), 100);
    } catch (e: any) {
      Alert.alert('Error', e.message || 'Failed to send message');
      setNewMessage(msgText); // Restore text on failure
      if (replyId) {
        const originalReply = messages.find(m => m.id === replyId);
        if (originalReply) setReplyToMessage(originalReply);
      }
    } finally {
      setSendingMessage(false);
    }
  };

  const onConnectionAction = async (targetId: number, action: 'send_request' | 'accept_request' | 'decline_request' | 'cancel_request') => {
    try {
      const msg = await handleConnection(userId, targetId, action);

      // Snappily update local UI state
      if (details) {
        const updatedParticipants = details.participants.map((p) => {
          if (p.id === targetId) {
            let nextStatus = p.connection_status;
            if (action === 'send_request') nextStatus = 'pending_sent';
            else if (action === 'accept_request') nextStatus = 'connected';
            else if (action === 'decline_request' || action === 'cancel_request') nextStatus = 'not_connected';

            return {
              ...p,
              connection_status: nextStatus
            };
          }
          return p;
        });

        setDetails({
          ...details,
          participants: updatedParticipants
        });
      }
    } catch (e: any) {
      Alert.alert('Error', e.message || 'Could not update connection request');
    }
  };

  const renderChatItem = ({ item }: { item: ChatMessage }) => {
    const isMe = item.user_id === userId;
    return (
      <SwipeableMessageRow item={item} isMe={isMe} onReply={setReplyToMessage}>
        <View style={[styles.chatRow, isMe ? styles.chatRowMe : styles.chatRowOther]}>
          {!isMe && (
            <View style={[styles.avatarCircle, { backgroundColor: item.color }]}>
              {item.profile_picture ? (
                <Image source={{ uri: item.profile_picture }} style={styles.avatarImage} />
              ) : (
                <Text style={styles.avatarText}>{item.initials}</Text>
              )}
            </View>
          )}
          <View style={styles.messageContentBlock}>
            {!isMe && (
              <Text style={[styles.chatSenderName, { color: colors.textSecondary }]}>
                {item.first_name} {item.last_name}
              </Text>
            )}
            <View
              style={[
                styles.chatBubble,
                isMe ? { backgroundColor: colors.chatBubbleMe } : { backgroundColor: colors.chatBubbleOther }
              ]}
            >
              {item.parent_id && (
                <View style={[styles.replyQuoteInBubble, { 
                  backgroundColor: isMe ? 'rgba(255,255,255,0.15)' : (darkTheme ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)'),
                  borderLeftColor: isMe ? '#FFFFFF' : '#10B981' 
                }]}>
                  <Text style={[styles.replyQuoteName, { color: isMe ? '#FFFFFF' : '#10B981' }]} numberOfLines={1}>
                    {item.parent_first_name} {item.parent_last_name}
                  </Text>
                  <Text style={[styles.replyQuoteText, { color: isMe ? '#E2E8F0' : colors.textSecondary }]} numberOfLines={2}>
                    {item.parent_message}
                  </Text>
                </View>
              )}
              <Text style={[styles.chatText, isMe ? styles.chatTextMe : { color: colors.chatTextOther }]}>
                {item.message}
              </Text>
            </View>
            <Text style={[styles.chatTimeText, { color: colors.chatTime, alignSelf: isMe ? 'flex-end' : 'flex-start' }]}>
              {new Date(item.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
            </Text>
          </View>
        </View>
      </SwipeableMessageRow>
    );
  };

  const renderParticipantItem = ({ item }: { item: any }) => {
    return (
      <View style={[styles.participantRow, { borderBottomColor: colors.border }]}>
        <View style={styles.participantLeft}>
          <View style={[styles.avatarCircleLg, { backgroundColor: item.color }]}>
            {item.profile_picture ? (
              <Image source={{ uri: item.profile_picture }} style={styles.avatarImage} />
            ) : (
              <Text style={styles.avatarTextLg}>{item.initials}</Text>
            )}
          </View>
          <View style={styles.participantDetails}>
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
              <Text style={[styles.participantName, { color: colors.text }]}>
                {item.first_name} {item.last_name}
              </Text>
              {item.is_me && (
                <View style={styles.meBadge}>
                  <Text style={styles.meBadgeText}>You</Text>
                </View>
              )}
            </View>
            <Text style={[styles.participantMotto, { color: colors.textSecondary }]} numberOfLines={1}>
              {item.motto}
            </Text>
          </View>
        </View>
      </View>
    );
  };

  if (loading) {
    return (
      <SafeAreaView style={[styles.container, { backgroundColor: colors.background }]}>
        <View style={styles.loadingWrapper}>
          <ActivityIndicator size="large" color={theme.colors.primary} />
          <Text style={{ marginTop: 12, color: colors.textSecondary, fontWeight: '600' }}>Loading community...</Text>
        </View>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: colors.background }]} edges={['top', 'left', 'right']}>
      {/* Header */}
      <View style={[styles.header, { borderBottomColor: colors.border }]}>
        <View style={styles.headerLeft}>
          <TouchableOpacity onPress={() => navigation.goBack()} style={styles.headerBtn}>
            <Ionicons name="chevron-back" size={24} color={colors.text} />
          </TouchableOpacity>
          <View style={styles.headerTitleContainer}>
            <Text style={[styles.headerTitle, { color: colors.text }]}>{challengeTitle}</Text>
            <Text style={[styles.headerSubtitle, { color: colors.textSecondary }]}>
              {details?.participants.length || 0} participants active
            </Text>
          </View>
        </View>
        <TouchableOpacity onPress={handleRefresh} style={styles.headerBtn}>
          <Ionicons name="refresh-outline" size={20} color={colors.text} />
        </TouchableOpacity>
      </View>

      <KeyboardAvoidingView
        style={{ flex: 1 }}
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
        keyboardVerticalOffset={Platform.OS === 'ios' ? 90 : 10}
      >
        <FlatList
          ref={chatListRef}
          data={messages}
          keyExtractor={(item) => item.id.toString()}
          renderItem={renderChatItem}
          contentContainerStyle={styles.chatListContent}
          onContentSizeChange={() => chatListRef.current?.scrollToEnd({ animated: true })}
          onLayout={() => chatListRef.current?.scrollToEnd({ animated: true })}
          ListEmptyComponent={
            <View style={styles.emptyChatContainer}>
              <Ionicons name="chatbubbles-outline" size={48} color={colors.textSecondary} />
              <Text style={[styles.emptyChatTitle, { color: colors.text }]}>No messages yet</Text>
              <Text style={[styles.emptyChatText, { color: colors.textSecondary }]}>
                Be the first to send a message to the group!
              </Text>
            </View>
          }
        />
        {/* Reply Preview Bar */}
        {replyToMessage && (
          <View style={[styles.replyPreviewBar, { backgroundColor: colors.cardBg, borderColor: colors.border }]}>
            <View style={[styles.replyPreviewBarInner, { borderLeftColor: '#10B981' }]}>
              <View style={{ flex: 1 }}>
                <Text style={[styles.replyPreviewName, { color: '#10B981' }]} numberOfLines={1}>
                  Replying to {replyToMessage.first_name} {replyToMessage.last_name}
                </Text>
                <Text style={[styles.replyPreviewText, { color: colors.textSecondary }]} numberOfLines={1}>
                  {replyToMessage.message}
                </Text>
              </View>
              <TouchableOpacity onPress={() => setReplyToMessage(null)} style={styles.replyPreviewCloseBtn}>
                <Ionicons name="close-circle" size={20} color={colors.textSecondary} />
              </TouchableOpacity>
            </View>
          </View>
        )}
        {/* Chat input bar */}
        <View style={[styles.inputContainer, { borderTopColor: colors.border, backgroundColor: colors.background }]}>
          <TextInput
            style={[styles.input, { backgroundColor: colors.inputBg, color: colors.text, borderColor: colors.border }]}
            value={newMessage}
            onChangeText={setNewMessage}
            placeholder="Post an update or word of motivation..."
            placeholderTextColor={colors.textSecondary}
            multiline
            maxLength={500}
          />
          <TouchableOpacity
            style={[styles.sendBtn, newMessage.trim() === '' && styles.sendBtnDisabled]}
            onPress={handleSendMessage}
            disabled={newMessage.trim() === '' || sendingMessage}
          >
            {sendingMessage ? (
              <ActivityIndicator size="small" color="#FFF" />
            ) : (
              <Ionicons name="send" size={16} color="#FFF" />
            )}
          </TouchableOpacity>
        </View>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#FFF',
  },
  loadingWrapper: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderBottomWidth: 1,
  },
  headerLeft: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
  },
  headerBtn: {
    padding: 6,
    borderRadius: 8,
  },
  headerTitleContainer: {
    marginLeft: 8,
    flex: 1,
  },
  headerTitle: {
    fontSize: 18,
    fontWeight: '800',
  },
  headerSubtitle: {
    fontSize: 12,
    marginTop: 2,
  },
  tabContainer: {
    flexDirection: 'row',
    borderBottomWidth: 1,
  },
  tab: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 14,
    gap: 8,
    borderBottomWidth: 2,
    borderBottomColor: 'transparent',
  },
  tabActive: {
    borderBottomColor: theme.colors.primary,
  },
  tabText: {
    fontSize: 14,
    fontWeight: '700',
  },
  tabTextActive: {
    color: theme.colors.primary,
  },
  // Chat list styles
  chatListContent: {
    padding: 16,
    flexGrow: 1,
  },
  chatRow: {
    flexDirection: 'row',
    marginBottom: 16,
    maxWidth: '80%',
  },
  chatRowMe: {
    alignSelf: 'flex-end',
    justifyContent: 'flex-end',
  },
  chatRowOther: {
    alignSelf: 'flex-start',
    justifyContent: 'flex-start',
  },
  avatarCircle: {
    width: 32,
    height: 32,
    borderRadius: 16,
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 8,
    alignSelf: 'flex-end',
  },
  avatarCircleLg: {
    width: 44,
    height: 44,
    borderRadius: 22,
    justifyContent: 'center',
    alignItems: 'center',
  },
  avatarText: {
    color: '#FFF',
    fontSize: 11,
    fontWeight: '800',
  },
  avatarTextLg: {
    color: '#FFF',
    fontSize: 16,
    fontWeight: '800',
  },
  avatarImage: {
    width: '100%',
    height: '100%',
    borderRadius: 22,
  },
  messageContentBlock: {
    flexDirection: 'column',
  },
  chatSenderName: {
    fontSize: 11,
    fontWeight: '700',
    marginBottom: 4,
    marginLeft: 4,
  },
  chatBubble: {
    borderRadius: 16,
    paddingHorizontal: 14,
    paddingVertical: 10,
    maxWidth: '100%',
  },
  chatText: {
    fontSize: 14,
    lineHeight: 20,
  },
  chatTextMe: {
    color: '#FFF',
  },
  chatTimeText: {
    fontSize: 9,
    fontWeight: '600',
    marginTop: 4,
    marginHorizontal: 4,
  },
  emptyChatContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 32,
    marginTop: 64,
  },
  emptyChatTitle: {
    fontSize: 16,
    fontWeight: '800',
    marginTop: 16,
    marginBottom: 8,
  },
  emptyChatText: {
    fontSize: 13,
    textAlign: 'center',
    lineHeight: 18,
  },
  // Chat input
  inputContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderTopWidth: 1,
    gap: 12,
  },
  input: {
    flex: 1,
    borderRadius: 20,
    borderWidth: 1,
    paddingHorizontal: 16,
    paddingTop: 10,
    paddingBottom: 10,
    fontSize: 14,
    maxHeight: 100,
  },
  sendBtn: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: theme.colors.primary,
    justifyContent: 'center',
    alignItems: 'center',
  },
  sendBtnDisabled: {
    opacity: 0.5,
  },
  // Participants styles
  participantsListContent: {
    paddingHorizontal: 16,
  },
  participantRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: 16,
    borderBottomWidth: 1,
  },
  participantLeft: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
    marginRight: 12,
  },
  participantDetails: {
    marginLeft: 12,
    flex: 1,
  },
  participantName: {
    fontSize: 15,
    fontWeight: '800',
  },
  meBadge: {
    backgroundColor: 'rgba(16, 185, 129, 0.12)',
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: 4,
  },
  meBadgeText: {
    color: theme.colors.primary,
    fontSize: 9,
    fontWeight: '800',
  },
  participantMotto: {
    fontSize: 12,
    marginTop: 2,
  },
  connectionActionWrapper: {
    justifyContent: 'center',
    alignItems: 'flex-end',
  },
  connBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 12,
    gap: 4,
  },
  connBtnAdd: {
    borderWidth: 1.5,
    backgroundColor: 'transparent',
  },
  connBtnTextAdd: {
    color: theme.colors.primary,
    fontSize: 12,
    fontWeight: '700',
  },
  connBtnPending: {
    backgroundColor: 'transparent',
    borderWidth: 1.5,
    borderColor: '#D1D5DB',
  },
  connBtnTextPending: {
    color: '#6B7280',
    fontSize: 12,
    fontWeight: '700',
  },
  connBtnAccept: {
    backgroundColor: theme.colors.primary,
  },
  connBtnTextAccept: {
    color: '#FFF',
    fontSize: 12,
    fontWeight: '700',
  },
  receivedActionGroup: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  declineIconBtn: {
    width: 28,
    height: 28,
    borderRadius: 14,
    borderWidth: 1.5,
    justifyContent: 'center',
    alignItems: 'center',
  },
  connectedBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: 'rgba(16, 185, 129, 0.08)',
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 12,
  },
  connectedBadgeText: {
    color: '#10B981',
    fontSize: 12,
    fontWeight: '700',
  },
  swipeContainer: {
    width: '100%',
    position: 'relative',
  },
  replyIconContainer: {
    position: 'absolute',
    left: 16,
    justifyContent: 'center',
    alignItems: 'center',
    width: 32,
    height: '100%',
    zIndex: 1,
  },
  replyQuoteInBubble: {
    borderLeftWidth: 3,
    paddingHorizontal: 8,
    paddingVertical: 6,
    borderRadius: 4,
    marginBottom: 6,
    minWidth: 140,
  },
  replyQuoteName: {
    fontWeight: '700',
    fontSize: 12,
    marginBottom: 2,
  },
  replyQuoteText: {
    fontSize: 11,
  },
  replyPreviewBar: {
    borderTopWidth: 1,
    paddingHorizontal: 16,
    paddingVertical: 8,
  },
  replyPreviewBarInner: {
    flexDirection: 'row',
    alignItems: 'center',
    borderLeftWidth: 3,
    paddingLeft: 8,
  },
  replyPreviewName: {
    fontWeight: '700',
    fontSize: 12,
    marginBottom: 2,
  },
  replyPreviewText: {
    fontSize: 12,
  },
  replyPreviewCloseBtn: {
    padding: 4,
    marginLeft: 8,
  },
});
