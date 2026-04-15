import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet, SafeAreaView, TouchableOpacity, ScrollView, TextInput, Image, ActivityIndicator, Alert } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useNavigation } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { RootStackParamList } from '../../../navigation/AppNavigator';
import { theme } from '../../../theme';
import { getRoutineLibrary, generateWorkoutDetails } from '../../../services/api/workoutService';

export const RoutineLibraryScreen = () => {
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const [loading, setLoading] = useState(true);
  const [routines, setRoutines] = useState<any[]>([]);
  const [searchQuery, setSearchQuery] = useState('');

  const userId = 1; // TODO: Get from context

  useEffect(() => {
    loadLibrary();
  }, []);

  const loadLibrary = async () => {
    setLoading(true);
    const data = await getRoutineLibrary(userId);
    setRoutines(data);
    setLoading(false);
  };

  const handleRoutinePress = async (routine: any) => {
    if (routine.workout && routine.workout.exercises) {
      navigation.navigate('ActiveWorkout', { workout: routine.workout, userId });
    } else {
      try {
        setLoading(true);
        const fullWorkout = await generateWorkoutDetails(userId, routine.name || 'Custom Routine');
        if (fullWorkout) {
          navigation.navigate('ActiveWorkout', { workout: fullWorkout, userId });
        } else {
          Alert.alert("Notice", "We couldn't load the details for this routine right now.");
        }
      } catch (err) {
        console.error('Generation error:', err);
        Alert.alert("Error", "Workout generation failed.");
      } finally {
        setLoading(false);
      }
    }
  };

  const filteredRoutines = (routines || []).filter(r => 
    r.name && r.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
    r.category && r.category.toLowerCase().includes(searchQuery.toLowerCase())
  );

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
          <Ionicons name="arrow-back" size={24} color={theme.colors.text} />
        </TouchableOpacity>
        <Text style={styles.title}>Routine Library</Text>
        <TouchableOpacity style={styles.filterButton} onPress={loadLibrary}>
          <Ionicons name="refresh-outline" size={24} color={theme.colors.primary} />
        </TouchableOpacity>
      </View>

      <View style={styles.searchContainer}>
        <Ionicons name="search-outline" size={20} color={theme.colors.textSecondary} style={styles.searchIcon} />
        <TextInput 
          style={styles.searchInput}
          placeholder="Search your AI routines..."
          placeholderTextColor={theme.colors.textSecondary}
          value={searchQuery}
          onChangeText={setSearchQuery}
        />
      </View>

      {loading ? (
        <View style={styles.loaderContainer}>
          <ActivityIndicator size="large" color={theme.colors.primary} />
          <Text style={styles.loadingText}>Loading your library...</Text>
        </View>
      ) : (
        <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
          <View style={styles.sectionHeader}>
            <Text style={styles.sectionTitle}>YOUR SAVED AI ROUTINES</Text>
            <Text style={styles.countText}>{filteredRoutines.length} items</Text>
          </View>

          {filteredRoutines && filteredRoutines.length > 0 ? (
            <View style={styles.routinesGrid}>
              {filteredRoutines.map((routine, index) => (
                <TouchableOpacity 
                  key={index} 
                  style={styles.routineCard}
                  onPress={() => handleRoutinePress(routine)}
                >
                  <Image source={{ uri: routine.image_url }} style={styles.routineImage} />
                  <View style={styles.routineOverlay}>
                    <View style={styles.levelBadge}>
                      <Text style={styles.levelText}>{routine.difficulty.toUpperCase()}</Text>
                    </View>
                    <View style={styles.routineInfo}>
                      <Text style={styles.routineTitle}>{routine.name}</Text>
                      <Text style={styles.routineSub}>{routine.duration} min • {routine.exercises_count} exercises</Text>
                    </View>
                  </View>
                </TouchableOpacity>
              ))}
            </View>
          ) : (
            <View style={styles.emptyState}>
              <Ionicons name="library-outline" size={60} color={theme.colors.border} />
              <Text style={styles.emptyTitle}>Library is empty</Text>
              <Text style={styles.emptySub}>Generate your first AI workout to see it here!</Text>
            </View>
          )}

          <View style={{ height: 40 }} />
        </ScrollView>
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
    padding: 8,
  },
  title: {
    fontSize: 18,
    fontWeight: '700',
    color: theme.colors.text,
  },
  filterButton: {
    padding: 8,
  },
  searchContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: theme.colors.surface,
    marginHorizontal: 20,
    paddingHorizontal: 15,
    height: 50,
    borderRadius: 12,
    marginBottom: 20,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  searchIcon: {
    marginRight: 10,
  },
  searchInput: {
    flex: 1,
    fontSize: 14,
    color: theme.colors.text,
  },
  loaderContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    gap: 15,
  },
  loadingText: {
    color: theme.colors.textSecondary,
    fontSize: 14,
  },
  content: {
    paddingHorizontal: 18,
  },
  sectionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 15,
  },
  sectionTitle: {
    fontSize: 11,
    fontWeight: '800',
    color: theme.colors.textSecondary,
    letterSpacing: 1,
  },
  countText: {
    fontSize: 12,
    color: theme.colors.textSecondary,
  },
  routinesGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
    gap: 14,
  },
  routineCard: {
    width: '47%',
    height: 200,
    borderRadius: 20,
    overflow: 'hidden',
    backgroundColor: theme.colors.surface,
    elevation: 4,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.1,
    shadowRadius: 8,
  },
  routineImage: {
    ...StyleSheet.absoluteFillObject,
    opacity: 0.8,
  },
  routineOverlay: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: 'rgba(0,0,0,0.35)',
    padding: 12,
    justifyContent: 'space-between',
  },
  levelBadge: {
    alignSelf: 'flex-start',
    backgroundColor: 'rgba(255,255,255,0.25)',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 6,
  },
  levelText: {
    fontSize: 8,
    fontWeight: '800',
    color: '#fff',
    letterSpacing: 0.5,
  },
  routineInfo: {
    gap: 2,
  },
  routineTitle: {
    fontSize: 14,
    fontWeight: '800',
    color: '#fff',
  },
  routineSub: {
    fontSize: 10,
    color: 'rgba(255,255,255,0.85)',
    fontWeight: '600',
  },
  emptyState: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 60,
    gap: 10,
  },
  emptyTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: theme.colors.text,
  },
  emptySub: {
    fontSize: 14,
    color: theme.colors.textSecondary,
    textAlign: 'center',
    paddingHorizontal: 40,
  }
});
