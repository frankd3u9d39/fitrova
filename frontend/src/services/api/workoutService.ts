import { API_BASE_URL } from './apiClient';

// Use PHP backend AI instead of Python service
const AI_SERVICE_URL = API_BASE_URL;

export interface Exercise {
  name: string;
  sets?: number;
  reps?: number;
  duration?: number;
  image_url?: string;
  video_url?: string;
  instructions?: string;
}

export interface Workout {
  name: string;
  exercises: Exercise[];
  exercises_count: number;
  duration: number;
  difficulty: string;
  type: string;
  scheduled_date?: string;
  day_name?: string;
}

export interface WorkoutRecommendation {
  todays_workout: Workout | null;
  upcoming_workouts: Workout[];
  missed_workouts: any[];
  recovery_score: number;
  status: string;
  fitness_level: string;
  weekly_progress: {
    completed: number;
    goal: number;
  };
}

// Fallback data if AI service is not available
const getFallbackData = (): WorkoutRecommendation => {
  const today = new Date();
  const tomorrow = new Date(today);
  tomorrow.setDate(tomorrow.getDate() + 1);
  const dayAfter = new Date(today);
  dayAfter.setDate(dayAfter.getDate() + 2);

  return {
    todays_workout: {
      name: 'Push Day A',
      exercises: [
        { name: 'Bench Press', sets: 3, reps: 10, image_url: 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80', video_url: 'https://d23dyxeqlo5psv.cloudfront.net/big_buck_bunny.mp4', instructions: 'Lie flat on the bench, grip the bar slightly wider than shoulder-width. Lower slowly to your chest and press up.' },
        { name: 'Overhead Press', sets: 3, reps: 12, image_url: 'https://images.unsplash.com/photo-1541534741688-6078c6bfb5c5?w=800&q=80', video_url: 'https://d23dyxeqlo5psv.cloudfront.net/big_buck_bunny.mp4', instructions: 'Stand tall with core tight. Press the dumbbells overhead until arms are fully extended, then lower with control.' },
        { name: 'Incline Dumbbell Press', sets: 3, reps: 10, image_url: 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?w=800&q=80', video_url: 'https://d23dyxeqlo5psv.cloudfront.net/big_buck_bunny.mp4', instructions: 'Set the bench to a 30-45 degree angle. Keep your elbows tucked at a 45 degree angle to your body as you press.' },
        { name: 'Lateral Raises', sets: 3, reps: 15, image_url: 'https://images.unsplash.com/photo-1581009146145-b5ef050c2e1e?w=800&q=80', video_url: 'https://d23dyxeqlo5psv.cloudfront.net/big_buck_bunny.mp4', instructions: 'With a slight bend in your elbows, raise the weights out to your sides until they reach shoulder height.' },
        { name: 'Tricep Pushdowns', sets: 3, reps: 12, image_url: 'https://images.unsplash.com/photo-1517836357463-d25dfeac3438?w=800&q=80', video_url: 'https://d23dyxeqlo5psv.cloudfront.net/big_buck_bunny.mp4', instructions: 'Keep your elbows glued to your sides. Push the cable attachment down until your arms are fully locked out.' },
        { name: 'Cable Flyes', sets: 3, reps: 15, image_url: 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?w=800&q=80', video_url: 'https://d23dyxeqlo5psv.cloudfront.net/big_buck_bunny.mp4', instructions: 'Bring the handles together in a hugging motion, squeezing your chest at the peak contraction.' }
      ],
      exercises_count: 6,
      duration: 55,
      difficulty: 'intermediate',
      type: 'strength',
      scheduled_date: today.toISOString().split('T')[0],
      day_name: today.toLocaleDateString('en-US', { weekday: 'long' }).toUpperCase()
    },
    upcoming_workouts: [
      {
        name: 'Pull Day B',
        exercises: [
          { name: 'Deadlifts', sets: 3, reps: 8, image_url: 'https://images.unsplash.com/photo-1541534741688-6078c6bfb5c5?w=800&q=80' },
          { name: 'Pull-ups', sets: 3, reps: 10, image_url: 'https://images.unsplash.com/photo-1598971639058-fab3c3109a00?w=800&q=80' },
          { name: 'Barbell Rows', sets: 3, reps: 10, image_url: 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?w=800&q=80' },
          { name: 'Face Pulls', sets: 3, reps: 15, image_url: 'https://images.unsplash.com/photo-1581009146145-b5ef050c2e1e?w=800&q=80' },
          { name: 'Hammer Curls', sets: 3, reps: 12, image_url: 'https://images.unsplash.com/photo-1517836357463-d25dfeac3438?w=800&q=80' },
          { name: 'Cable Rows', sets: 3, reps: 12, image_url: 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80' },
          { name: 'Shrugs', sets: 3, reps: 15, image_url: 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?w=800&q=80' },
          { name: 'Reverse Flyes', sets: 3, reps: 15, image_url: 'https://images.unsplash.com/photo-1581009146145-b5ef050c2e1e?w=800&q=80' }
        ],
        exercises_count: 8,
        duration: 65,
        difficulty: 'intermediate',
        type: 'strength',
        scheduled_date: tomorrow.toISOString().split('T')[0],
        day_name: tomorrow.toLocaleDateString('en-US', { weekday: 'long' }).toUpperCase()
      },
      {
        name: 'Leg Day',
        exercises: [
          { name: 'Back Squats', sets: 3, reps: 10, image_url: 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?w=800&q=80' },
          { name: 'Romanian Deadlifts', sets: 3, reps: 10, image_url: 'https://images.unsplash.com/photo-1581009146145-b5ef050c2e1e?w=800&q=80' },
          { name: 'Leg Press', sets: 3, reps: 12, image_url: 'https://images.unsplash.com/photo-1517836357463-d25dfeac3438?w=800&q=80' },
          { name: 'Walking Lunges', sets: 3, reps: 12, image_url: 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80' },
          { name: 'Leg Extensions', sets: 3, reps: 15, image_url: 'https://images.unsplash.com/photo-1541534741688-6078c6bfb5c5?w=800&q=80' }
        ],
        exercises_count: 5,
        duration: 45,
        difficulty: 'intermediate',
        type: 'strength',
        scheduled_date: dayAfter.toISOString().split('T')[0],
        day_name: dayAfter.toLocaleDateString('en-US', { weekday: 'long' }).toUpperCase()
      }
    ],
    missed_workouts: [],
    recovery_score: 98,
    status: 'READY FOR SESSION',
    fitness_level: 'intermediate',
    weekly_progress: {
      completed: 3,
      goal: 4
    }
  };
};

export const getWorkoutRecommendations = async (userId: number): Promise<WorkoutRecommendation> => {
  try {
    console.log('Fetching AI workout recommendations from PHP backend');
    
    // Create a timeout promise to give Gemini AI enough time to generate the JSON (up to 45s)
    const timeoutPromise = new Promise((_, reject) => {
      setTimeout(() => reject(new Error('Request timeout')), 45000); // 45 second timeout for AI
    });
    
    // Create the fetch promise - using Gemini AI endpoint
    const fetchPromise = fetch(`${AI_SERVICE_URL}/ai_workout_gemini.php`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ user_id: userId }),
    });
    
    // Race between fetch and timeout
    const response = await Promise.race([fetchPromise, timeoutPromise]) as Response;
    const result = await response.json();

    if (!response.ok || result.status !== 'success') {
      throw new Error(result.message || 'Failed to fetch workout recommendations');
    }

    console.log('✅ AI workout recommendations loaded from', result.ai_provider || 'PHP backend');
    return result.data;
  } catch (error) {
    console.error('⚠️ AI service unavailable, using fallback data. Debug reason:', error);
    // Return fallback data instead of throwing error
    return getFallbackData();
  }
};

export const saveWorkoutPlan = async (userId: number, workouts: Workout[]): Promise<void> => {
  try {
    const timeoutPromise = new Promise((_, reject) => {
      setTimeout(() => reject(new Error('Request timeout')), 5000);
    });
    
    const fetchPromise = fetch(`${AI_SERVICE_URL}/api/save-workout-plan`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ user_id: userId, workouts }),
    });
    
    const response = await Promise.race([fetchPromise, timeoutPromise]) as Response;
    
    if (!response.ok) {
      throw new Error(`Failed to save workout plan: ${response.status}`);
    }
    
    const result = await response.json();

    if (result.status !== 'success') {
      throw new Error(result.message || 'Failed to save workout plan');
    }
  } catch (error) {
    console.error('Save workout plan error:', error);
    throw error;
  }
};

export const completeWorkout = async (
  userId: number,
  workoutName: string,
  duration: number
): Promise<void> => {
  try {
    const timeoutPromise = new Promise((_, reject) => {
      setTimeout(() => reject(new Error('Request timeout')), 5000);
    });
    
    const fetchPromise = fetch(`${AI_SERVICE_URL}/complete_workout.php`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ user_id: userId, workout_name: workoutName, duration }),
    });
    
    const response = await Promise.race([fetchPromise, timeoutPromise]) as Response;
    
    if (!response.ok) {
      throw new Error(`Failed to complete workout: ${response.status}`);
    }
    
    const result = await response.json();

    if (result.status !== 'success') {
      throw new Error(result.message || 'Failed to complete workout');
    }
    
    console.log('✅ Workout logged to AI service');
  } catch (error) {
    console.warn('⚠️ AI service unavailable for workout logging');
    throw error;
  }
};

export const getWorkoutSchedule = async (userId: number): Promise<any[]> => {
  try {
    const response = await fetch(`${AI_SERVICE_URL}/get_schedule.php?user_id=${userId}`);
    const result = await response.json();
    if (result.status === 'success') {
      return result.schedule;
    }
    return [];
  } catch (error) {
    console.error('Error fetching schedule:', error);
    return [];
  }
};

export const getRoutineLibrary = async (userId: number): Promise<any[]> => {
  try {
    const response = await fetch(`${AI_SERVICE_URL}/get_library.php?user_id=${userId}`);
    const result = await response.json();
    if (result.status === 'success') {
      return result.library;
    }
    return [];
  } catch (error) {
    console.error('Error fetching library:', error);
    return [];
  }
};
export const generateWorkoutDetails = async (userId: number, workoutName: string): Promise<Workout | null> => {
  try {
    const response = await fetch(`${AI_SERVICE_URL}/generate_workout_detail.php`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ user_id: userId, workout_name: workoutName }),
    });
    const result = await response.json();
    if (result.status === 'success') {
      return result.workout;
    }
    return null;
  } catch (error) {
    console.error('Error generating workout details:', error);
    return null;
  }
};
