import React, { useState, useEffect } from 'react';
import { 
  View, 
  Text, 
  StyleSheet, 
  TouchableOpacity, 
  TextInput, 
  FlatList, 
  Image, 
  ActivityIndicator,
  ScrollView,
  Dimensions
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { useNavigation, useFocusEffect } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { RootStackParamList } from '../../../navigation/types';
import { theme } from '../../../theme';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { API_BASE_URL } from '../../../services/api/apiClient';
import { WebView } from 'react-native-webview';
import YoutubePlayer from 'react-native-youtube-iframe';

const { width } = Dimensions.get('window');

export const YouTubeAnalysisScreen = () => {
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const [searchQuery, setSearchQuery] = useState('');
  const [videos, setVideos] = useState([]);
  const [loading, setLoading] = useState(false);
  const [selectedVideo, setSelectedVideo] = useState<any>(null);
  const [analysis, setAnalysis] = useState<any>(null);
  const [analyzing, setAnalyzing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [darkTheme, setDarkTheme] = useState(false);

  const colors = {
    background:    darkTheme ? '#0F172A' : theme.colors.background,
    cardBg:        darkTheme ? '#1E293B' : theme.colors.surface,
    text:          darkTheme ? '#F8FAFC' : theme.colors.text,
    textSecondary: darkTheme ? '#94A3B8' : theme.colors.textSecondary,
    border:        darkTheme ? '#334155' : theme.colors.border,
    inputBg:       darkTheme ? '#1E293B' : theme.colors.surface,
  };

  useFocusEffect(
    React.useCallback(() => {
      (async () => {
        try {
          const saved = await AsyncStorage.getItem('user_session');
          if (saved) {
            const session = JSON.parse(saved);
            const userId = session.id || 1;
            const prefs = await AsyncStorage.getItem(`user_prefs_${userId}`);
            if (prefs) {
              const parsed = JSON.parse(prefs);
              if (parsed.darkTheme !== undefined) setDarkTheme(parsed.darkTheme);
            }
          }
        } catch (e) {}
      })();
    }, [])
  );

  const searchVideos = async () => {
    if (!searchQuery.trim()) return;
    setLoading(true);
    try {
      const response = await fetch(`${API_BASE_URL}/app/controllers/ai/youtube_workout_controller.php?action=search&query=${encodeURIComponent(searchQuery)}`);
      const data = await response.json();
      setVideos(data.items || []);
    } catch (error) {
      console.error('Search error:', error);
    } finally {
      setLoading(false);
    }
  };

  const analyzeVideo = async (video: any) => {
    setSelectedVideo(video);
    setAnalysis(null);
    setError(null);
    setAnalyzing(true);
    
    const videoUrl = `https://www.youtube.com/watch?v=${video.id.videoId}`;
    
    try {
      const response = await fetch(`${API_BASE_URL}/app/controllers/ai/youtube_workout_controller.php?action=analyze`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          youtube_url: videoUrl,
          exercise_name: searchQuery || 'Workout'
        })
      });
      const data = await response.json();
      if (data.error) {
        setError(data.error);
      } else {
        setAnalysis(data.analysis);
      }
    } catch (err: any) {
      console.error('Analysis error:', err);
      setError('Connection to AI Service failed. Please ensure the AI backend server is running.');
    } finally {
      setAnalyzing(false);
    }
  };

  const renderVideoItem = ({ item }: { item: any }) => (
    <TouchableOpacity style={[styles.videoCard, { backgroundColor: colors.cardBg }]} onPress={() => analyzeVideo(item)}>
      <Image source={{ uri: item.snippet.thumbnails.medium.url }} style={styles.thumbnail} />
      <View style={styles.videoInfo}>
        <Text style={[styles.videoTitle, { color: colors.text }]} numberOfLines={2}>{item.snippet.title}</Text>
        <Text style={[styles.channelTitle, { color: colors.textSecondary }]}>{item.snippet.channelTitle}</Text>
      </View>
    </TouchableOpacity>
  );

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: colors.background }]}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
          <Ionicons name="arrow-back" size={24} color={colors.text} />
        </TouchableOpacity>
        <Text style={[styles.title, { color: colors.text }]}>AI Video Analysis</Text>
        <View style={{ width: 40 }} />
      </View>

      <View style={styles.searchContainer}>
        <View style={[styles.searchBar, { backgroundColor: colors.inputBg }]}>
          <Ionicons name="search" size={20} color={colors.textSecondary} />
          <TextInput
            style={[styles.input, { color: colors.text }]}
            placeholder="Search exercise (e.g. Squat, Pushup)"
            placeholderTextColor={colors.textSecondary}
            value={searchQuery}
            onChangeText={setSearchQuery}
            onSubmitEditing={searchVideos}
          />
        </View>
        <TouchableOpacity style={styles.searchBtn} onPress={searchVideos}>
          <Text style={styles.searchBtnText}>Search</Text>
        </TouchableOpacity>
      </View>

      {selectedVideo ? (
        <ScrollView style={[styles.analysisContent, { backgroundColor: colors.background }]}>
          <View style={styles.videoPlayerContainer}>
            <YoutubePlayer
              height={220}
              play={true}
              videoId={selectedVideo.id.videoId}
              initialPlayerParams={{
                preventFullScreen: false,
                controls: true,
                origin: 'https://www.youtube.com',
              }}
            />
          </View>
          
          <TouchableOpacity style={styles.closeVideo} onPress={() => setSelectedVideo(null)}>
            <Ionicons name="close-circle" size={30} color={theme.colors.primary} />
            <Text style={styles.closeVideoText}>Choose Another Video</Text>
          </TouchableOpacity>

          <View style={styles.analysisSection}>
            <Text style={[styles.analysisHeader, { color: colors.textSecondary }]}>AI FORM COACHING</Text>
            
            {analyzing ? (
              <View style={styles.analyzingContainer}>
                <ActivityIndicator size="large" color={theme.colors.primary} />
                <Text style={[styles.analyzingText, { color: colors.textSecondary }]}>Extracting Pose Data & Analyzing...</Text>
              </View>
            ) : error ? (
              <View style={[styles.errorContainer, { backgroundColor: colors.cardBg }]}>
                <Ionicons name="alert-circle" size={40} color={theme.colors.warning} />
                <Text style={[styles.errorText, { color: colors.textSecondary }]}>{error}</Text>
              </View>
            ) : analysis ? (
              <View style={[styles.resultCard, { backgroundColor: colors.cardBg }]}>
                <View style={styles.scoreRow}>
                  <View style={[
                    styles.scoreBadge,
                    analysis.status === 'CAUTION' && { backgroundColor: theme.colors.warning },
                    analysis.status === 'GOOD' && { backgroundColor: theme.colors.secondary }
                  ]}>
                    <Text style={styles.scoreValue}>{analysis.accuracy_score}%</Text>
                    <Text style={styles.scoreLabel}>{analysis.status || 'ACCURACY'}</Text>
                  </View>
                  <Text style={[styles.exerciseName, { color: colors.text }]}>{analysis.exercise}</Text>
                </View>

                <Text style={[styles.summaryText, { color: colors.textSecondary }]}>{analysis.summary}</Text>

                <Text style={[styles.tipsHeader, { color: colors.text }]}>COACHING TIPS</Text>
                {analysis.pro_tips.map((tip: string, index: number) => (
                  <View key={index} style={styles.tipItem}>
                    <Ionicons name="flash" size={16} color={theme.colors.primary} />
                    <Text style={[styles.tipText, { color: colors.textSecondary }]}>{tip}</Text>
                  </View>
                ))}

                <TouchableOpacity 
                  style={styles.mirrorBtn} 
                  onPress={() => navigation.navigate('FormCheck' as any, { 
                    exercise: analysis.exercise,
                    source: 'youtube',
                    targetScore: analysis.accuracy_score
                  })}
                >
                  <Text style={styles.mirrorBtnText}>NOW YOU TRY IT</Text>
                  <Ionicons name="camera" size={20} color="#fff" />
                </TouchableOpacity>
              </View>
            ) : (
              <Text style={[styles.placeholderText, { color: colors.textSecondary }]}>Select a video to start analysis</Text>
            )}
          </View>
        </ScrollView>
      ) : (
        <View style={styles.listContainer}>
          {loading ? (
            <ActivityIndicator size="large" color={theme.colors.primary} style={{ marginTop: 50 }} />
          ) : (
            <FlatList
              data={videos}
              renderItem={renderVideoItem}
              keyExtractor={(item: any) => item.id.videoId}
              contentContainerStyle={styles.listContent}
              ListEmptyComponent={
                <View style={styles.emptyContainer}>
                  <Ionicons name="videocam-outline" size={80} color={colors.cardBg} />
                  <Text style={[styles.emptyText, { color: colors.textSecondary }]}>Search for a workout video to get AI form coaching</Text>
                </View>
              }
            />
          )}
        </View>
      )}
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
    paddingHorizontal: 20,
    paddingVertical: 15,
  },
  backButton: {
    padding: 5,
  },
  title: {
    fontSize: 18,
    fontWeight: '700',
    color: theme.colors.text,
  },
  searchContainer: {
    flexDirection: 'row',
    paddingHorizontal: 20,
    paddingVertical: 10,
    gap: 10,
  },
  searchBar: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: theme.colors.surface,
    borderRadius: 12,
    paddingHorizontal: 12,
    height: 48,
  },
  input: {
    flex: 1,
    marginLeft: 10,
    color: theme.colors.text,
    fontSize: 14,
  },
  searchBtn: {
    backgroundColor: theme.colors.primary,
    borderRadius: 12,
    paddingHorizontal: 15,
    justifyContent: 'center',
    alignItems: 'center',
  },
  searchBtnText: {
    color: '#fff',
    fontWeight: '700',
  },
  listContainer: {
    flex: 1,
  },
  listContent: {
    padding: 20,
  },
  videoCard: {
    flexDirection: 'row',
    backgroundColor: theme.colors.surface,
    borderRadius: 16,
    marginBottom: 15,
    overflow: 'hidden',
    height: 100,
  },
  thumbnail: {
    width: 140,
    height: '100%',
  },
  videoInfo: {
    flex: 1,
    padding: 12,
    justifyContent: 'center',
  },
  videoTitle: {
    fontSize: 14,
    fontWeight: '700',
    color: theme.colors.text,
    marginBottom: 4,
  },
  channelTitle: {
    fontSize: 12,
    color: theme.colors.textSecondary,
  },
  analysisContent: {
    flex: 1,
  },
  videoPlayerContainer: {
    width: '100%',
    height: 220,
    backgroundColor: '#000',
  },
  webView: {
    flex: 1,
  },
  closeVideo: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 15,
    gap: 10,
  },
  closeVideoText: {
    color: theme.colors.primary,
    fontWeight: '700',
  },
  analysisSection: {
    padding: 20,
  },
  analysisHeader: {
    fontSize: 12,
    fontWeight: '800',
    color: theme.colors.textSecondary,
    letterSpacing: 1,
    marginBottom: 15,
  },
  analyzingContainer: {
    alignItems: 'center',
    paddingVertical: 40,
    gap: 15,
  },
  analyzingText: {
    color: theme.colors.textSecondary,
    fontWeight: '600',
  },
  resultCard: {
    backgroundColor: theme.colors.surface,
    borderRadius: 24,
    padding: 20,
  },
  scoreRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 15,
    marginBottom: 15,
  },
  scoreBadge: {
    backgroundColor: theme.colors.primary,
    padding: 10,
    borderRadius: 16,
    alignItems: 'center',
    minWidth: 80,
  },
  scoreValue: {
    fontSize: 20,
    fontWeight: '900',
    color: '#fff',
  },
  scoreLabel: {
    fontSize: 8,
    fontWeight: '800',
    color: '#fff',
    opacity: 0.8,
  },
  exerciseName: {
    fontSize: 20,
    fontWeight: '800',
    color: theme.colors.text,
    flex: 1,
  },
  summaryText: {
    fontSize: 15,
    color: theme.colors.textSecondary,
    lineHeight: 22,
    marginBottom: 20,
  },
  tipsHeader: {
    fontSize: 14,
    fontWeight: '800',
    color: theme.colors.text,
    marginBottom: 10,
  },
  tipItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    marginBottom: 8,
  },
  tipText: {
    fontSize: 14,
    color: theme.colors.textSecondary,
    fontWeight: '500',
  },
  mirrorBtn: {
    backgroundColor: theme.colors.primary,
    flexDirection: 'row',
    height: 54,
    borderRadius: 16,
    justifyContent: 'center',
    alignItems: 'center',
    gap: 10,
    marginTop: 25,
  },
  mirrorBtnText: {
    color: '#fff',
    fontWeight: '800',
    letterSpacing: 1,
  },
  emptyContainer: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 100,
  },
  emptyText: {
    color: theme.colors.textSecondary,
    textAlign: 'center',
    marginTop: 20,
    fontSize: 16,
    paddingHorizontal: 40,
  },
  placeholderText: {
    textAlign: 'center',
    color: theme.colors.textSecondary,
    paddingVertical: 50,
  },
  errorContainer: {
    alignItems: 'center',
    paddingVertical: 40,
    gap: 15,
    backgroundColor: theme.colors.surface,
    borderRadius: 24,
    padding: 20,
  },
  errorText: {
    color: theme.colors.textSecondary,
    fontWeight: '600',
    textAlign: 'center',
    fontSize: 14,
  }
});
