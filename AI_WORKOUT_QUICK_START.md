# 🤖 AI Workout System - Quick Start

## What It Does

✅ Generates personalized workout plans based on your fitness level
✅ Automatically reschedules missed workouts
✅ Tracks recovery and weekly progress
✅ Adapts to your goals (strength, cardio, weight loss)

## Setup (3 Steps)

### 1️⃣ Install Python Dependencies

```bash
cd ai-service
pip install -r requirements.txt
```

### 2️⃣ Start the AI Service

```bash
cd ai-service/api
python recommend-workout.py
```

You should see:
```
* Running on http://0.0.0.0:5001
```

### 3️⃣ Test in Your App

1. Make sure XAMPP MySQL is running
2. Start your React Native app: `cd frontend && npm start`
3. Navigate to the Workout tab
4. You'll see AI-generated workouts!

## How It Works

### AI Logic

The system analyzes:
- Your workout history (beginner/intermediate/advanced)
- Your fitness goals (from profile)
- Your activity level
- Days since last workout

Then generates:
- Today's workout (personalized)
- 7-day workout schedule
- Recovery score
- Weekly progress tracking

### Automatic Rescheduling

If you miss a workout:
1. AI detects the missed workout
2. Automatically reschedules it to tomorrow
3. Shows alert in the app
4. Keeps you on track!

## Example Workouts

### Beginner
- Full Body Strength A (45 min)
- Upper Body Focus (40 min)
- Lower Body Power (50 min)

### Intermediate
- Push Day A (55 min) - 6 exercises
- Pull Day B (65 min) - 8 exercises
- Leg Day (45 min) - 5 exercises

### Advanced
- Heavy Push Day (70 min)
- Heavy Pull Day (75 min)
- Leg Hypertrophy (60 min)

## What You'll See in the App

**Workout Screen Shows:**
- Recovery score (e.g., "98% Recovery")
- Today's workout with exercise count and duration
- Missed workout alerts
- Weekly progress (e.g., "3/4 workouts completed")
- Upcoming workouts for the week

**Features:**
- "START WORKOUT" button to begin
- AI Tools section
- Upcoming workouts list
- Real-time data from database

## API Endpoints

The AI service provides 3 endpoints:

1. **GET Recommendations**: `POST /api/recommend-workout`
2. **Save Plan**: `POST /api/save-workout-plan`
3. **Complete Workout**: `POST /api/complete-workout`

## Troubleshooting

### "Failed to load workout data"
- Check AI service is running: `http://localhost:5001`
- Verify MySQL is running in XAMPP
- Check console for errors

### "No workouts showing"
- Make sure you ran the database migration
- Check that user profile exists
- Verify user_id is correct (default is 1)

### Python errors
- Install dependencies: `pip install -r requirements.txt`
- Use Python 3.8 or higher

## Files Created

1. `ai-service/api/recommend-workout.py` - Main AI service
2. `ai-service/requirements.txt` - Python dependencies
3. `frontend/src/services/api/workoutService.ts` - Frontend API client
4. `frontend/src/screens/workout/WorkoutScreen/WorkoutScreen.tsx` - Updated UI

## Next Steps

1. Start the AI service
2. Open your app
3. Go to Workout tab
4. See your personalized workout plan!

The AI will automatically:
- Generate workouts based on your level
- Track your progress
- Reschedule missed workouts
- Calculate recovery scores

## Summary

You now have an AI-powered workout system that generates personalized plans and keeps you on track. No more dummy data - everything is real and adapts to your fitness journey!
