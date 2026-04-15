import React, { useState, useRef, useEffect } from 'react';
import { 
  View, 
  Text, 
  StyleSheet, 
  SafeAreaView, 
  TouchableOpacity, 
  Image, 
  ActivityIndicator, 
  Alert,
  Dimensions,
  Animated,
  ScrollView
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useNavigation } from '@react-navigation/native';
import { CameraView, useCameraPermissions } from 'expo-camera';
import { Video, Audio } from 'expo-av';
import * as Speech from 'expo-speech';
import * as Haptics from 'expo-haptics';
import { theme } from '../../../theme';
import { API_BASE_URL } from '../../../services/api/apiClient';

const { width, height } = Dimensions.get('window');

export const FormCheckScreen = () => {
  const navigation = useNavigation();
  const [permission, requestPermission] = useCameraPermissions();
  const cameraRef = useRef<CameraView>(null);
  
  const [isAnalyzing, setIsAnalyzing] = useState(false);
  const [isCountingDown, setIsCountingDown] = useState(false);
  const [countdown, setCountdown] = useState(5);
  const [analysisResult, setAnalysisResult] = useState<any>(null);
  const [capturedImage, setCapturedImage] = useState<string | null>(null);

  const scanAnim = useRef(new Animated.Value(0)).current;

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

  if (!permission) {
    return <View style={styles.centered}><ActivityIndicator size="large" color={theme.colors.primary} /></View>;
  }

  if (!permission.granted) {
    return (
      <SafeAreaView style={styles.container}>
        <View style={styles.permissionContainer}>
          <Text style={styles.permissionText}>We need your permission to show the camera</Text>
          <TouchableOpacity style={styles.permissionBtn} onPress={requestPermission}>
            <Text style={styles.permissionBtnText}>Grant Permission</Text>
          </TouchableOpacity>
        </View>
      </SafeAreaView>
    );
  }

  const startAnalysis = () => {
    setAnalysisResult(null);
    setCapturedImage(null);
    setIsCountingDown(true);
    setCountdown(5);
    
    let count = 5;
    const interval = setInterval(() => {
      count -= 1;
      setCountdown(count);
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
      if (count === 0) {
        clearInterval(interval);
        setIsCountingDown(false);
        captureAndAnalyze();
      }
    }, 1000);
  };

  const speakResult = (text: string) => {
    Speech.speak(text, { pitch: 1.0, rate: 0.9 });
  };

  const captureAndAnalyze = async () => {
    if (!cameraRef.current) return;
    
    try {
      setIsAnalyzing(true);
      
      const photo = await cameraRef.current.takePictureAsync({
        base64: true,
        quality: 0.5,
      });

      if (!photo?.base64) throw new Error("Failed to capture image");
      
      setCapturedImage(photo.uri);

      const response = await fetch(`${API_BASE_URL}/ai_form_analyzer.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          user_id: 1,
          image: photo.base64,
          exercise: 'Detect automatically' 
        }),
      });

      const result = await response.json();
      setAnalysisResult(result);
      
      // Auto-speak the summary for a hands-free experience
      if (result.summary) {
        speakResult(result.summary);
      }
      
    } catch (error) {
      console.error('Analysis error:', error);
      Alert.alert("Analysis Failed", "Could not connect to the AI trainer.");
    } finally {
      setIsAnalyzing(false);
    }
  };

  const translateY = scanAnim.interpolate({
    inputRange: [0, 1],
    outputRange: [0, height * 0.55],
  });

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
          <Ionicons name="close" size={28} color={theme.colors.text} />
        </TouchableOpacity>
        <Text style={styles.title}>AI Form Check</Text>
        <View style={{ width: 44 }} />
      </View>

      <View style={styles.content}>
        <View style={styles.cameraFrame}>
          {!analysisResult && !capturedImage ? (
            <CameraView 
              ref={cameraRef}
              style={StyleSheet.absoluteFill} 
              facing="front"
            />
          ) : (
            <Image source={{ uri: capturedImage! }} style={StyleSheet.absoluteFill} />
          )}

          {isAnalyzing && (
            <Animated.View style={[styles.scanLine, { transform: [{ translateY }] }]} />
          )}

          {isCountingDown && (
            <View style={styles.countdownOverlay}>
              <Text style={styles.countdownText}>{countdown}</Text>
              <Text style={styles.countdownSub}>Get Ready!</Text>
            </View>
          )}

          {/* AI Decorative Overlays */}
          {!analysisResult && !isCountingDown && !isAnalyzing && (
            <View style={styles.skeletonFrame}>
               <View style={[styles.skeletonJoint, { top: '30%', left: '48%' }]} />
               <View style={[styles.skeletonJoint, { top: '45%', left: '40%' }]} />
               <View style={[styles.skeletonJoint, { top: '45%', left: '56%' }]} />
               <View style={[styles.skeletonJoint, { top: '65%', left: '38%' }]} />
               <View style={[styles.skeletonJoint, { top: '65%', left: '58%' }]} />
            </View>
          )}
        </View>

        {analysisResult ? (
          <ScrollView style={styles.resultScroll} showsVerticalScrollIndicator={false}>
            <View style={[styles.scoreBadge, { backgroundColor: analysisResult.status === 'GOOD' ? '#10B981' : '#F59E0B' }]}>
               <Text style={styles.scoreText}>{(analysisResult.score || '0')}% FORM ACCURACY</Text>
            </View>
            
            <Text style={styles.resultTitle}>{analysisResult.detected_exercise || 'Movement Detected'}</Text>
            <Text style={styles.resultSummary}>{analysisResult.summary || 'Step back so we can see your full body for a better analysis.'}</Text>
            
            <View style={styles.tipsContainer}>
              <Text style={styles.tipsHeader}>AI CORRECTIONS</Text>
              {(analysisResult.tips && analysisResult.tips.length > 0) ? analysisResult.tips.map((tip: string, index: number) => (
                <View key={index} style={styles.tipItem}>
                  <Ionicons name="bulb-outline" size={18} color={theme.colors.primary} />
                  <Text style={styles.tipText}>{tip}</Text>
                </View>
              )) : (
                <View style={styles.tipItem}>
                  <Ionicons name="checkmark-circle" size={18} color="#10B981" />
                  <Text style={styles.tipText}>Form looks solid! Keep maintaining this posture.</Text>
                </View>
              )}
            </View>

            <TouchableOpacity style={styles.retryBtn} onPress={() => { setAnalysisResult(null); setCapturedImage(null); }}>
              <Text style={styles.retryBtnText}>CHECK AGAIN</Text>
            </TouchableOpacity>
          </ScrollView>
        ) : (
          <>
            <View style={styles.instructions}>
              <Text style={styles.instructionTitle}>
                {isAnalyzing ? "Analyzing Form..." : "Prepare for Scan"}
              </Text>
              <Text style={styles.instructionText}>
                {isAnalyzing 
                  ? "Our AI is analyzing your joint alignment and range of motion. Please wait."
                  : "Position yourself 5-7 feet away. The AI will capture your movement in 5 seconds."}
              </Text>
            </View>

            {!isAnalyzing && !isCountingDown && (
              <TouchableOpacity 
                style={styles.startButton} 
                onPress={startAnalysis}
                disabled={isAnalyzing || isCountingDown}
              >
                <Text style={styles.startButtonText}>START SCAN</Text>
                <Ionicons name="sparkles" size={20} color="#fff" />
              </TouchableOpacity>
            )}
            
            {(isAnalyzing || isCountingDown) && (
              <View style={styles.loadingBar}>
                <ActivityIndicator color={theme.colors.primary} />
                <Text style={styles.loadingInfo}>{isCountingDown ? "Waiting for snap..." : "Processing AI Vision..."}</Text>
              </View>
            )}
          </>
        )}
      </View>
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
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
    paddingVertical: 40,
  },
  backButton: {
    padding: 8,
  },
  title: {
    fontSize: 18,
    fontWeight: '700',
    color: theme.colors.text,
  },
  content: {
    flex: 1,
    padding: 20,
  },
  cameraFrame: {
    flex: 0.55,
    backgroundColor: '#000',
    borderRadius: 24,
    overflow: 'hidden',
    position: 'relative',
    borderWidth: 2,
    borderColor: 'rgba(16, 185, 129, 0.3)',
    marginBottom: 20,
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
    fontSize: 100,
    fontWeight: '900',
    color: '#fff',
  },
  countdownSub: {
    fontSize: 20,
    color: '#fff',
    fontWeight: '700',
    letterSpacing: 2,
  },
  skeletonFrame: {
    ...StyleSheet.absoluteFillObject,
  },
  skeletonJoint: {
    position: 'absolute',
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: theme.colors.primary,
    opacity: 0.6,
  },
  instructions: {
    paddingVertical: 10,
  },
  instructionTitle: {
    fontSize: 24,
    fontWeight: '800',
    color: theme.colors.text,
    marginBottom: 8,
  },
  instructionText: {
    fontSize: 15,
    color: theme.colors.textSecondary,
    lineHeight: 22,
    marginBottom: 24,
  },
  startButton: {
    backgroundColor: theme.colors.primary,
    flexDirection: 'row',
    height: 56,
    borderRadius: 16,
    justifyContent: 'center',
    alignItems: 'center',
    gap: 10,
  },
  startButtonText: {
    fontSize: 16,
    fontWeight: '800',
    color: '#fff',
    letterSpacing: 1,
  },
  permissionContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 40,
  },
  permissionText: {
    fontSize: 16,
    textAlign: 'center',
    marginBottom: 20,
    color: theme.colors.textSecondary,
  },
  permissionBtn: {
    backgroundColor: theme.colors.primary,
    paddingHorizontal: 25,
    paddingVertical: 12,
    borderRadius: 12,
  },
  permissionBtnText: {
    color: '#fff',
    fontWeight: '700',
  },
  loadingBar: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    backgroundColor: theme.colors.surface,
    padding: 15,
    borderRadius: 16,
  },
  loadingInfo: {
    color: theme.colors.textSecondary,
    fontWeight: '600',
  },
  resultScroll: {
    flex: 1,
  },
  scoreBadge: {
    alignSelf: 'flex-start',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 8,
    marginBottom: 10,
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
    marginBottom: 20,
  },
  tipsContainer: {
    gap: 12,
    marginBottom: 30,
  },
  tipsHeader: {
    fontSize: 12,
    fontWeight: '800',
    color: theme.colors.textSecondary,
    letterSpacing: 1,
    marginBottom: 5,
  },
  tipItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    backgroundColor: theme.colors.surface,
    padding: 15,
    borderRadius: 16,
  },
  tipText: {
    flex: 1,
    fontSize: 14,
    color: theme.colors.text,
    fontWeight: '600',
  },
  retryBtn: {
    borderWidth: 1,
    borderColor: theme.colors.primary,
    height: 50,
    borderRadius: 12,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 30,
  },
  retryBtnText: {
    color: theme.colors.primary,
    fontWeight: '800',
    letterSpacing: 1,
  }
});
