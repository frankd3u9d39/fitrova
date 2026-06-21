import { SafeAreaView } from 'react-native-safe-area-context';
import { CustomAlert } from '../../../components/common/CustomAlert';
import React, { useState, useRef, useEffect } from 'react';
import { 
  View, 
  Text, 
  StyleSheet, 
  TouchableOpacity, 
  Image, 
  ActivityIndicator, 
  Dimensions,
  Animated,
  ScrollView,
  Alert,
  Linking
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useNavigation, useRoute } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { RootStackParamList } from '../../../navigation/types';
import { CameraView, useCameraPermissions, useMicrophonePermissions } from 'expo-camera';
import * as VideoThumbnails from 'expo-video-thumbnails';
import * as Speech from 'expo-speech';
import * as Haptics from 'expo-haptics';
import { theme } from '../../../theme';
import { API_BASE_URL } from '../../../services/api/apiClient';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { SubscriptionUpgradeModal } from '../../../components/common/SubscriptionUpgradeModal';

const { width, height } = Dimensions.get('window');

export const FormCheckScreen = () => {
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const route = useRoute<any>();
  const routeExercise = route.params?.exercise || 'Detect automatically';
  const [cameraPermission, requestCameraPermission] = useCameraPermissions();
  const [micPermission, requestMicPermission] = useMicrophonePermissions();
  const cameraRef = useRef<CameraView>(null);

  const [facing, setFacing] = useState<'front' | 'back'>('back');
  const [userId, setUserId] = useState<number>(1);
  const [firstName, setFirstName] = useState<string>('User');
  const [isAnalyzing, setIsAnalyzing] = useState(false);
  const [isRecording, setIsRecording] = useState(false);
  const [isExtractingFrames, setIsExtractingFrames] = useState(false);
  const [isCountingDown, setIsCountingDown] = useState(false);
  const [countdown, setCountdown] = useState(3);
  const [recordProgress, setRecordProgress] = useState(0); // 0-5 seconds
  const [analysisResult, setAnalysisResult] = useState<any>(null);
  const [capturedImage, setCapturedImage] = useState<string | null>(null);
  const [paywallVisible, setPaywallVisible] = useState(false);
  const [paywallData, setPaywallData] = useState<any>(null);
  const [darkTheme, setDarkTheme] = useState(false);

  const colors = {
    background:    darkTheme ? '#0F172A' : '#FFFFFF',
    cardBg:        darkTheme ? '#1E293B' : '#F9FAFB',
    text:          darkTheme ? '#F8FAFC' : '#1F2937',
    textSecondary: darkTheme ? '#94A3B8' : '#6B7280',
    tipBg:         darkTheme ? '#1E293B' : '#F9FAFB',
    border:        darkTheme ? '#334155' : '#E5E7EB',
  };

  const scanAnim = useRef(new Animated.Value(0)).current;
  const flipAnim = useRef(new Animated.Value(1)).current;

  useEffect(() => {
    const loadUserSession = async () => {
      try {
        const savedSession = await AsyncStorage.getItem('user_session');
        if (savedSession) {
          const session = JSON.parse(savedSession);
          if (session.id) setUserId(session.id);
          if (session.firstName) setFirstName(session.firstName);
          // Load dark mode pref
          const prefs = await AsyncStorage.getItem(`user_prefs_${session.id || 1}`);
          if (prefs) {
            const parsed = JSON.parse(prefs);
            if (parsed.darkTheme !== undefined) setDarkTheme(parsed.darkTheme);
          }
        }
      } catch (e) {
        console.error('Error loading session in FormCheck:', e);
      }
    };
    loadUserSession();
  }, []);

  useEffect(() => {
    if (isAnalyzing) {
      Animated.loop(
        Animated.sequence([
          Animated.timing(scanAnim, { toValue: 1, duration: 2000, useNativeDriver: true }),
          Animated.timing(scanAnim, { toValue: 0, duration: 2000, useNativeDriver: true }),
        ])
      ).start();
    } else {
      scanAnim.stopAnimation();
    }
  }, [isAnalyzing]);

  if (!cameraPermission || !micPermission) {
    return <View style={styles.centered}><ActivityIndicator size="large" color={theme.colors.primary} /></View>;
  }

  if (!cameraPermission.granted || !micPermission.granted) {
    return (
      <SafeAreaView style={styles.container}>
        <View style={styles.permissionContainer}>
          <Ionicons name="camera-outline" size={64} color={theme.colors.primary} />
          <Text style={styles.permissionTitle}>Camera Access Needed</Text>
          <Text style={styles.permissionText}>We need your camera and microphone to record your workout form.</Text>
          <TouchableOpacity style={styles.permissionBtn} onPress={async () => {
            const camRes = !cameraPermission.granted ? await requestCameraPermission() : cameraPermission;
            const micRes = !micPermission.granted ? await requestMicPermission() : micPermission;
            if (!camRes.granted || !micRes.granted) {
              Alert.alert(
                'Permissions Required',
                'Fitrova needs camera and microphone access. Please enable them in your device settings.',
                [
                  { text: 'Cancel', style: 'cancel' },
                  { text: 'Open Settings', onPress: () => Linking.openSettings() }
                ]
              );
            }
          }}>
            <Text style={styles.permissionBtnText}>Grant Permissions</Text>
          </TouchableOpacity>
        </View>
      </SafeAreaView>
    );
  }

  const toggleFacing = () => {
    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    Animated.sequence([
      Animated.timing(flipAnim, { toValue: 0, duration: 150, useNativeDriver: true }),
      Animated.timing(flipAnim, { toValue: 1, duration: 150, useNativeDriver: true }),
    ]).start();
    setFacing(prev => prev === 'front' ? 'back' : 'front');
  };

  const startAnalysis = () => {
    setAnalysisResult(null);
    setCapturedImage(null);
    setIsCountingDown(true);
    setCountdown(3);
    setRecordProgress(0);

    let count = 3;
    const interval = setInterval(() => {
      count -= 1;
      setCountdown(count);
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
      if (count === 0) {
        clearInterval(interval);
        setIsCountingDown(false);
        recordAndAnalyze();
      }
    }, 1000);
  };

  const speakResult = (text: string) => {
    Speech.speak(text, { pitch: 1.0, rate: 0.9 });
  };

  // Record 5s video → extract 3 key frames → send lightweight JPEGs to Gemini
  // This gives Gemini motion context (start/mid/end of rep) without sending a
  // huge video file that would time out on Render's free tier.
  const recordAndAnalyze = async () => {
    if (!cameraRef.current) return;

    try {
      setIsRecording(true);
      Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);

      // ── Step 1: Record 5-second clip ─────────────────────────────────
      // Progress ticker (visual feedback every second)
      let elapsed = 0;
      const progressTimer = setInterval(() => {
        elapsed += 1;
        setRecordProgress(elapsed);
        if (elapsed >= 5) clearInterval(progressTimer);
      }, 1000);

      const videoResult = await cameraRef.current.recordAsync({ maxDuration: 5 });
      clearInterval(progressTimer);
      setIsRecording(false);

      if (!videoResult?.uri) throw new Error('Video recording failed');
      const videoUri = videoResult.uri;

      // Show the first frame as preview while we process
      setIsExtractingFrames(true);

      // ── Step 2: Extract 3 key frames at 0.5s, 2.5s, 4.5s ────────────
      const timestamps = [500, 2500, 4500]; // milliseconds
      const frameUris: string[] = [];

      for (const ts of timestamps) {
        try {
          const { uri } = await VideoThumbnails.getThumbnailAsync(videoUri, {
            time: ts,
            quality: 0.8,
          });
          frameUris.push(uri);
        } catch (thumbErr) {
          console.warn(`Frame at ${ts}ms failed, skipping:`, thumbErr);
        }
      }

      if (frameUris.length === 0) throw new Error('Could not extract any frames from video');

      // Use first frame as the preview image
      setCapturedImage(frameUris[0]);
      setIsExtractingFrames(false);
      setIsAnalyzing(true);

      // ── Step 3: Build multipart payload with all frames ───────────────
      const formData = new FormData();
      frameUris.forEach((uri, idx) => {
        formData.append(`frame_${idx}`, {
          uri,
          name: `frame_${idx}.jpg`,
          type: 'image/jpeg',
        } as any);
      });
      formData.append('user_id', userId.toString());
      formData.append('exercise', routeExercise);

      const response = await fetch(`${API_BASE_URL}/app/controllers/ai/ai_form_analyzer.php`, {
        method: 'POST',
        body: formData,
      });

      const result = await response.json();

      if (response.status === 403 || result.status === 'subscription_locked') {
        setIsAnalyzing(false);
        setCapturedImage(null);
        setPaywallData(result);
        setPaywallVisible(true);
        return;
      }

      setAnalysisResult(result);
      if (result.summary) speakResult(result.summary);

    } catch (error: any) {
      console.error('Form analysis error:', error);
      const msg = error?.name === 'AbortError'
        ? 'Analysis timed out. Please try again.'
        : 'Could not complete the analysis. Check your connection.';
      CustomAlert.alert('Analysis Failed', msg);
    } finally {
      setIsRecording(false);
      setIsExtractingFrames(false);
      setIsAnalyzing(false);
      setRecordProgress(0);
    }
  };

  const translateY = scanAnim.interpolate({
    inputRange: [0, 1],
    outputRange: [0, height * 0.85],
  });

  const isBusy = isAnalyzing || isCountingDown || isRecording || isExtractingFrames;

  // ── RESULTS VIEW ──────────────────────────────────────
  if (analysisResult) {
    return (
      <SafeAreaView style={[styles.container, { backgroundColor: colors.background }]}>
        <View style={[styles.header, { borderBottomColor: colors.border, borderBottomWidth: 1 }]}>
          <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
            <Ionicons name="close" size={28} color={colors.text} />
          </TouchableOpacity>
          <Text style={[styles.title, { color: colors.text }]}>AI Form Check</Text>
          <View style={{ width: 44 }} />
        </View>

        <ScrollView style={styles.resultScroll} contentContainerStyle={styles.resultContent} showsVerticalScrollIndicator={false}>
          <View style={[styles.scoreBadge, { backgroundColor: analysisResult.status === 'GOOD' ? '#10B981' : '#F59E0B' }]}>
            <Text style={styles.scoreText}>{(analysisResult.score || '0')}% FORM ACCURACY</Text>
          </View>

          <Text style={[styles.resultTitle, { color: colors.text }]}>{analysisResult.detected_exercise || 'Movement Detected'}</Text>
          <Text style={[styles.resultSummary, { color: colors.textSecondary }]}>{analysisResult.summary || 'Step back so we can see your full body for a better analysis.'}</Text>

          <View style={styles.tipsContainer}>
            <Text style={[styles.tipsHeader, { color: colors.textSecondary }]}>AI CORRECTIONS</Text>
            {(analysisResult.tips && analysisResult.tips.length > 0) ? analysisResult.tips.map((tip: string, index: number) => (
              <View key={index} style={[styles.tipItem, { backgroundColor: colors.tipBg }]}>
                <Ionicons name="bulb-outline" size={18} color={theme.colors.primary} />
                <Text style={[styles.tipText, { color: colors.text }]}>{tip}</Text>
              </View>
            )) : (
              <View style={[styles.tipItem, { backgroundColor: colors.tipBg }]}>
                <Ionicons name="checkmark-circle" size={18} color="#10B981" />
                <Text style={[styles.tipText, { color: colors.text }]}>Form looks solid! Keep maintaining this posture.</Text>
              </View>
            )}
          </View>

          <TouchableOpacity style={styles.retryBtn} onPress={() => { setAnalysisResult(null); setCapturedImage(null); setRecordProgress(0); }}>
            <Ionicons name="refresh" size={18} color={theme.colors.primary} />
            <Text style={styles.retryBtnText}>CHECK AGAIN</Text>
          </TouchableOpacity>
        </ScrollView>
      </SafeAreaView>
    );
  }

  // ── CAMERA VIEW (full screen) ─────────────────────────
  return (
    <View style={styles.fullScreen}>
      {!capturedImage ? (
        <Animated.View style={[StyleSheet.absoluteFill, { opacity: flipAnim }]}>
          <CameraView
            ref={cameraRef}
            style={StyleSheet.absoluteFill}
            facing={facing}
            mode="video"
          />
        </Animated.View>
      ) : (
        <Image source={{ uri: capturedImage }} style={StyleSheet.absoluteFill} />
      )}

      {isAnalyzing && (
        <Animated.View style={[styles.scanLine, { transform: [{ translateY }] }]} />
      )}

      {!isBusy && !capturedImage && (
        <View style={styles.bodyGuideFrame}>
          <View style={[styles.guideCorner, styles.guideTopLeft]} />
          <View style={[styles.guideCorner, styles.guideTopRight]} />
          <View style={[styles.guideCorner, styles.guideBottomLeft]} />
          <View style={[styles.guideCorner, styles.guideBottomRight]} />
          <Text style={styles.guideHint}>Stand here · Full body visible</Text>
        </View>
      )}

      {isCountingDown && (
        <View style={styles.countdownOverlay}>
          <Text style={styles.countdownText}>{countdown}</Text>
          <Text style={styles.countdownSub}>Get Ready!</Text>
        </View>
      )}

      {isRecording && (
        <View style={styles.recordingBadge}>
          <View style={styles.recordingDot} />
          <Text style={styles.recordingText}>● REC {recordProgress}/5s</Text>
        </View>
      )}

      <SafeAreaView style={styles.topBar}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.topBtn}>
          <Ionicons name="close" size={24} color="#fff" />
        </TouchableOpacity>
        <Text style={styles.topTitle}>AI Form Check</Text>
        <TouchableOpacity
          style={[styles.topBtn, isBusy && styles.topBtnDisabled]}
          onPress={toggleFacing}
          disabled={isBusy}
        >
          <Ionicons name="camera-reverse-outline" size={26} color="#fff" />
        </TouchableOpacity>
      </SafeAreaView>

      <View style={styles.bottomPanel}>
        {!isBusy ? (
          <>
            <Text style={styles.bottomHint}>
              {routeExercise !== 'Detect automatically' ? `Target: ${routeExercise}\n` : ''}
              {facing === 'back' ? 'Back camera · position 5-7 ft away' : 'Front camera · step back for full body'}
            </Text>
            <TouchableOpacity style={styles.scanButton} onPress={startAnalysis}>
              <View style={styles.scanButtonInner}>
                <Ionicons name="videocam" size={28} color="#fff" />
              </View>
            </TouchableOpacity>
            <Text style={styles.scanLabel}>TAP TO RECORD 5s</Text>
          </>
        ) : (
          <View style={styles.busyRow}>
            <ActivityIndicator color={theme.colors.primary} size="small" />
            <Text style={styles.busyText}>
              {isCountingDown
                ? `Get ready... ${countdown}`
                : isRecording
                ? `Recording... ${recordProgress}/5s`
                : isExtractingFrames
                ? 'Extracting frames...'
                : 'Analyzing your form...'}
            </Text>
          </View>
        )}
      </View>

      <SubscriptionUpgradeModal
        visible={paywallVisible}
        title={paywallData?.title || "✨ Unlock AI Form Check"}
        message={paywallData?.message || "Trial used. Upgrade to Advanced Premium for unlimited posture & joint alignment biomechanics checking!"}
        pricingOptions={paywallData?.pricing_options}
        onClose={() => {
          setPaywallVisible(false);
          navigation.goBack();
        }}
        onUpgrade={() => {
          setPaywallVisible(false);
          navigation.navigate('SubscriptionSelection', { userId, firstName });
        }}
      />
    </View>
  );
};

const styles = StyleSheet.create({
  fullScreen: {
    flex: 1,
    backgroundColor: '#000',
  },
  topBar: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    paddingTop: 8,
    paddingBottom: 12,
    backgroundColor: 'rgba(0,0,0,0.45)',
  },
  topBtn: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: 'rgba(255,255,255,0.18)',
    justifyContent: 'center',
    alignItems: 'center',
  },
  topBtnDisabled: {
    opacity: 0.3,
  },
  topTitle: {
    color: '#fff',
    fontSize: 17,
    fontWeight: '700',
    letterSpacing: 0.3,
  },
  bottomPanel: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    paddingBottom: 48,
    paddingTop: 24,
    paddingHorizontal: 24,
    backgroundColor: 'rgba(0,0,0,0.55)',
    alignItems: 'center',
    gap: 10,
  },
  bottomHint: {
    color: 'rgba(255,255,255,0.7)',
    fontSize: 13,
    textAlign: 'center',
    fontWeight: '500',
    marginBottom: 4,
  },
  scanButton: {
    width: 80,
    height: 80,
    borderRadius: 40,
    borderWidth: 3,
    borderColor: 'rgba(255,255,255,0.45)',
    justifyContent: 'center',
    alignItems: 'center',
  },
  scanButtonInner: {
    width: 64,
    height: 64,
    borderRadius: 32,
    backgroundColor: theme.colors.primary,
    justifyContent: 'center',
    alignItems: 'center',
  },
  scanLabel: {
    color: 'rgba(255,255,255,0.55)',
    fontSize: 11,
    fontWeight: '700',
    letterSpacing: 2,
  },
  busyRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingVertical: 14,
  },
  busyText: {
    color: '#fff',
    fontSize: 15,
    fontWeight: '600',
  },
  bodyGuideFrame: {
    position: 'absolute',
    top: '14%',
    bottom: '22%',
    left: '18%',
    right: '18%',
    justifyContent: 'flex-end',
    alignItems: 'center',
  },
  guideCorner: {
    position: 'absolute',
    width: 28,
    height: 28,
    borderColor: 'rgba(16,185,129,0.85)',
  },
  guideTopLeft: {
    top: 0,
    left: 0,
    borderTopWidth: 3,
    borderLeftWidth: 3,
  },
  guideTopRight: {
    top: 0,
    right: 0,
    borderTopWidth: 3,
    borderRightWidth: 3,
  },
  guideBottomLeft: {
    bottom: 30,
    left: 0,
    borderBottomWidth: 3,
    borderLeftWidth: 3,
  },
  guideBottomRight: {
    bottom: 30,
    right: 0,
    borderBottomWidth: 3,
    borderRightWidth: 3,
  },
  guideHint: {
    color: 'rgba(16,185,129,0.9)',
    fontSize: 11,
    fontWeight: '600',
    letterSpacing: 0.5,
    backgroundColor: 'rgba(0,0,0,0.45)',
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 8,
  },
  scanLine: {
    position: 'absolute',
    width: '100%',
    height: 3,
    backgroundColor: theme.colors.primary,
    shadowColor: theme.colors.primary,
    shadowOffset: { width: 0, height: 0 },
    shadowOpacity: 1,
    shadowRadius: 10,
    zIndex: 10,
  },
  countdownOverlay: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: 'rgba(0,0,0,0.4)',
    justifyContent: 'center',
    alignItems: 'center',
  },
  countdownText: {
    fontSize: 120,
    fontWeight: '900',
    color: '#fff',
    lineHeight: 130,
  },
  countdownSub: {
    fontSize: 22,
    color: '#fff',
    fontWeight: '700',
    letterSpacing: 3,
  },
  recordingBadge: {
    position: 'absolute',
    top: 100,
    alignSelf: 'center',
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: 'rgba(0,0,0,0.6)',
    paddingHorizontal: 14,
    paddingVertical: 7,
    borderRadius: 20,
    gap: 8,
  },
  recordingDot: {
    width: 10,
    height: 10,
    borderRadius: 5,
    backgroundColor: '#EF4444',
  },
  recordingText: {
    color: '#fff',
    fontSize: 13,
    fontWeight: '800',
    letterSpacing: 1.5,
  },
  container: {
    flex: 1,
    backgroundColor: theme.colors.background,
  },
  centered: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 20,
    paddingVertical: 16,
    paddingTop: 8,
  },
  backButton: {
    padding: 8,
  },
  title: {
    fontSize: 18,
    fontWeight: '700',
    color: theme.colors.text,
  },
  resultScroll: {
    flex: 1,
  },
  resultContent: {
    padding: 24,
    paddingBottom: 48,
  },
  scoreBadge: {
    alignSelf: 'flex-start',
    paddingHorizontal: 14,
    paddingVertical: 7,
    borderRadius: 10,
    marginBottom: 12,
  },
  scoreText: {
    color: '#fff',
    fontSize: 12,
    fontWeight: '900',
    letterSpacing: 1,
  },
  resultTitle: {
    fontSize: 28,
    fontWeight: '900',
    color: theme.colors.text,
    marginBottom: 8,
  },
  resultSummary: {
    fontSize: 15,
    color: theme.colors.textSecondary,
    lineHeight: 22,
    marginBottom: 24,
  },
  tipsContainer: {
    gap: 12,
    marginBottom: 32,
  },
  tipsHeader: {
    fontSize: 11,
    fontWeight: '800',
    color: theme.colors.textSecondary,
    letterSpacing: 1.5,
    marginBottom: 4,
  },
  tipItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    backgroundColor: theme.colors.surface,
    padding: 16,
    borderRadius: 16,
  },
  tipText: {
    flex: 1,
    fontSize: 14,
    color: theme.colors.text,
    fontWeight: '600',
  },
  retryBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    borderWidth: 1.5,
    borderColor: theme.colors.primary,
    height: 52,
    borderRadius: 14,
    marginBottom: 20,
  },
  retryBtnText: {
    color: theme.colors.primary,
    fontWeight: '800',
    letterSpacing: 1,
    fontSize: 14,
  },
  permissionContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 40,
    gap: 16,
  },
  permissionTitle: {
    fontSize: 22,
    fontWeight: '800',
    color: theme.colors.text,
    textAlign: 'center',
  },
  permissionText: {
    fontSize: 15,
    textAlign: 'center',
    color: theme.colors.textSecondary,
    lineHeight: 22,
  },
  permissionBtn: {
    backgroundColor: theme.colors.primary,
    paddingHorizontal: 28,
    paddingVertical: 14,
    borderRadius: 14,
    marginTop: 8,
  },
  permissionBtnText: {
    color: '#fff',
    fontWeight: '700',
    fontSize: 15,
  },
});
