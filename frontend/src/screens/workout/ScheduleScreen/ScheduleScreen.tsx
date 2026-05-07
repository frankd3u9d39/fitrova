import { SafeAreaView } from 'react-native-safe-area-context';
import { CustomAlert } from '../../../components/common/CustomAlert';
import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet,  TouchableOpacity, ScrollView, ActivityIndicator, Alert } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useNavigation } from '@react-navigation/native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { RootStackParamList } from '../../../navigation/types';
import { theme } from '../../../theme';
import { getWorkoutSchedule, generateWorkoutDetails } from '../../../services/api/workoutService';

export const ScheduleScreen = () => {
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const [loading, setLoading] = useState(true);
  const [days, setDays] = useState<any[]>([]);

  const userId = 1; // TODO: Get from context

  useEffect(() => {
    loadSchedule();
  }, []);

  const loadSchedule = async () => {
    setLoading(true);
    const data = await getWorkoutSchedule(userId);
    setDays(data);
    setLoading(false);
  };

  const handleDayPress = async (item: any) => {
    if (item.workout && item.workout.exercises) {
      navigation.navigate('ActiveWorkout', { workout: item.workout, userId });
    } else {
      try {
        setLoading(true);
        const fullWorkout = await generateWorkoutDetails(userId, item.title || 'Daily Session');
        if (fullWorkout) {
          navigation.navigate('ActiveWorkout', { workout: fullWorkout, userId });
        } else {
          CustomAlert.alert("Notice", "We couldn't generate this session. Try again later.");
        }
      } catch (err) {
        console.error('Generation error:', err);
        CustomAlert.alert("Error", "Workout generation failed.");
      } finally {
        setLoading(false);
      }
    }
  };

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
          <Ionicons name="arrow-back" size={24} color={theme.colors.text} />
        </TouchableOpacity>
        <Text style={styles.title}>Workout Schedule</Text>
        <TouchableOpacity style={styles.calendarButton} onPress={loadSchedule}>
          <Ionicons name="calendar-outline" size={24} color={theme.colors.primary} />
        </TouchableOpacity>
      </View>

      {loading ? (
        <View style={styles.loaderContainer}>
          <ActivityIndicator size="large" color={theme.colors.primary} />
          <Text style={styles.loadingText}>Fetching your schedule...</Text>
        </View>
      ) : (
        <ScrollView contentContainerStyle={styles.content}>
          <View style={styles.weekContainer}>
            {(days || []).length > 0 ? (days || []).slice(0, 7).map((item, index) => (
              <View key={index} style={styles.dayColumn}>
                <Text style={[styles.dayText, item.status === 'today' && styles.todayText]}>{item.day}</Text>
                <View style={[
                  styles.dateCircle, 
                  item.status === 'today' && styles.todayCircle,
                  item.status === 'completed' && styles.completedCircle
                ]}>
                  <Text style={[
                    styles.dateText, 
                    item.status === 'today' && styles.todayDateText,
                    item.status === 'completed' && styles.completedDateText
                  ]}>{item.date}</Text>
                </View>
                {item.status === 'completed' && (
                  <View style={styles.checkMark}>
                    <Ionicons name="checkmark-circle" size={14} color={theme.colors.primary} />
                  </View>
                )}
              </View>
            )) : (
              <Text style={styles.emptyText}>No sessions logged yet.</Text>
            )}
          </View>

          <View style={styles.scheduleList}>
            <Text style={styles.sectionTitle}>SESSIONS</Text>
            {(days || []).map((item, index) => (
              <TouchableOpacity 
                key={index} 
                style={styles.scheduleItem}
                onPress={() => handleDayPress(item)}
              >
                <View style={[styles.timeDot, item.status === 'today' && styles.todayDot, item.status === 'completed' && styles.completedDot]} />
                <View style={styles.itemInfo}>
                  <Text style={styles.itemTitle}>{item.title}</Text>
                  <Text style={styles.itemSub}>{item.status === 'today' ? 'Today' : item.full_date}</Text>
                </View>
                {item.status === 'completed' ? (
                  <Ionicons name="checkmark-done" size={20} color={theme.colors.primary} />
                ) : (
                  <Ionicons name="chevron-forward" size={20} color={theme.colors.border} />
                )}
              </TouchableOpacity>
            ))}
          </View>
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
  calendarButton: {
    padding: 8,
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
    padding: 20,
  },
  weekContainer: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    backgroundColor: theme.colors.surface,
    padding: 15,
    borderRadius: 20,
    marginBottom: 30,
    elevation: 2,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 10,
  },
  dayColumn: {
    alignItems: 'center',
    gap: 8,
  },
  dayText: {
    fontSize: 10,
    fontWeight: '700',
    color: theme.colors.textSecondary,
  },
  todayText: {
    color: theme.colors.primary,
  },
  dateCircle: {
    width: 36,
    height: 36,
    borderRadius: 18,
    justifyContent: 'center',
    alignItems: 'center',
    borderWidth: 1,
    borderColor: 'transparent',
  },
  todayCircle: {
    backgroundColor: theme.colors.primary,
  },
  completedCircle: {
    backgroundColor: 'rgba(16, 185, 129, 0.1)',
  },
  dateText: {
    fontSize: 14,
    fontWeight: '600',
    color: theme.colors.text,
  },
  todayDateText: {
    color: '#fff',
  },
  completedDateText: {
    color: theme.colors.primary,
  },
  checkMark: {
    position: 'absolute',
    bottom: -5,
    right: -2,
  },
  scheduleList: {
    gap: 15,
  },
  sectionTitle: {
    fontSize: 12,
    fontWeight: '700',
    color: theme.colors.textSecondary,
    letterSpacing: 1,
    marginBottom: 5,
  },
  scheduleItem: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: theme.colors.surface,
    padding: 15,
    borderRadius: 16,
    gap: 15,
  },
  timeDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: theme.colors.border,
  },
  todayDot: {
    backgroundColor: theme.colors.primary,
  },
  completedDot: {
    backgroundColor: theme.colors.primary,
  },
  itemInfo: {
    flex: 1,
  },
  itemTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: theme.colors.text,
    marginBottom: 4,
  },
  itemSub: {
    fontSize: 13,
    color: theme.colors.textSecondary,
  },
  emptyText: {
    flex: 1,
    textAlign: 'center',
    color: theme.colors.textSecondary,
    paddingVertical: 20,
  }
});
