# AI-Powered Workout Recommendation System

## Overview

This AI service generates personalized workout plans based on user profiles, fitness goals, and workout history. It automatically reschedules missed workouts to keep users on track.

## Features

✅ **Personalized Workout Plans** - AI generates workouts based on:
- User's fitness level (beginner/intermediate/advanced)
- Fitness goals (strength, cardio, weight loss, muscle gain)
- Activity level
- Workout history

✅ **Automatic Rescheduling** - Missed workouts are automatically rescheduled to the next available day

✅ **Recovery Tracking** - Calculates recovery score based on workout frequency

✅ **Progress Monitoring** - Tracks weekly workout completion

✅ **Smart Scheduling** - Creates 7-day workout plans with appropriate rest days

## Setup Instructions

### 1. Install Python Dependencies

```bash
cd ai-service
pip install -r requirements.txt
```

Or if you're using a virtual environment:

```bash
cd ai-service
python -m venv venv
venv\Scripts\activate  # On Windows
# source venv/bin/activate  # On Mac/Linux
pip install -r requirements.txt
```

### 2. Configure Database

The AI service connects to your existing `fitrova_db` MySQL database. Make sure:
- XAMPP MySQL is running
- Database `fitrova_db` exists
- Tables from migration are created

### 3. Start the AI Service

```bash
cd ai-service/api
python recommend-workout.py
```

The service will start on `http://localhost:5001`

### 4. Test the API

Open your browser or use Postman:

```
POST http://localhost:5001/api/recommend-workout
Body: { "user_id": 1 }
```

You should get a response with workout recommendations.

## API Endpoints

### 1. Get Workout Recommendations

**POST** `/api/recommend-workout`

Request:
```json
{
  "user_id": 1
}
```

Response:
```json
{
  "status": "success",
  "data": {
    "todays_workout": {
      "name": "Push Day A",
      "exercises": ["Bench Press", "Overhead Press", ...],
      "exercises_count": 6,
      "duration": 55,
      "difficulty": "intermediate",
      "type": "strength"
    },
    "upcoming_workouts": [...],
    "missed_workouts": [],
    "recovery_score": 98,
    "status": "READY FOR SESSION",
    "fitness_level": "intermediate",
    "weekly_progress": {
      "completed": 3,
      "goal": 4
    }
  }
}
```

### 2. Save Workout Plan

**POST** `/api/save-workout-plan`

Request:
```json
{
  "user_id": 1,
  "workouts": [
    {
      "name": "Push Day A",
      "exercises": ["Bench Press", "Overhead Press"],
      "duration": 55,
      "difficulty": "intermediate",
      "type": "strength"
    }
  ]
}
```

### 3. Complete Workout

**POST** `/api/complete-workout`

Request:
```json
{
  "user_id": 1,
  "workout_name": "Push Day A",
  "duration": 55
}
```

## How the AI Works

### Fitness Level Determination

The AI analyzes:
1. **Workout History** - Number of completed workouts
   - 0-10 workouts = Beginner
   - 11-20 workouts = Intermediate
   - 20+ workouts = Advanced

2. **Activity Level** - From user profile
   - Sedentary/Light = Beginner
   - Moderate = Intermediate
   - Active/Very Active = Advanced

### Workout Type Selection

Based on user's fitness goal:
- **"Weight Loss"** or **"Cardio"** → Cardio workouts
- **"Muscle Gain"** or **"Strength"** → Strength workouts
- **Other goals** → Mixed workouts

### Workout Scheduling

- **Beginner**: 3 workouts per week
- **Intermediate**: 4 workouts per week
- **Advanced**: 5 workouts per week

Rest days are automatically scheduled between workout days.

### Recovery Score Calculation

```
Recovery Score = 70 + (days_since_last_workout * 10)
Maximum: 98%
```

- 0 days since last workout = 70% recovery
- 1 day = 80% recovery
- 2 days = 90% recovery
- 3+ days = 98% recovery

### Missed Workout Rescheduling

The AI checks for:
1. Scheduled workouts in `workout_plans` table
2. Completed workouts in `workout_logs` table
3. If a workout was scheduled but not completed, it's rescheduled to tomorrow

## Workout Templates

The AI has pre-built workout templates for:

### Strength Training
- **Beginner**: Full body workouts, basic movements
- **Intermediate**: Push/Pull/Legs split
- **Advanced**: Heavy compound lifts, advanced techniques

### Cardio
- **Beginner**: Walking, light jogging
- **Intermediate**: HIIT, interval training
- **Advanced**: Advanced HIIT, endurance training

### Mixed
- **Beginner**: Bodyweight circuits
- **Intermediate**: Strength + cardio combinations
- **Advanced**: CrossFit-style workouts

## Integration with Frontend

The frontend `WorkoutScreen` automatically:
1. Fetches workout recommendations on load
2. Displays today's workout
3. Shows upcoming workouts
4. Alerts user about missed workouts
5. Tracks weekly progress

## Troubleshooting

### AI Service Won't Start

**Error**: `ModuleNotFoundError: No module named 'flask'`
- Solution: Run `pip install -r requirements.txt`

**Error**: `mysql.connector.errors.ProgrammingError: 1049 (42000): Unknown database 'fitrova_db'`
- Solution: Run the database migration first

### Frontend Can't Connect

**Error**: `Network request failed`
- Solution: Make sure AI service is running on `http://localhost:5001`
- Check that both frontend and AI service are running

### No Workouts Generated

**Issue**: API returns empty workout list
- Solution: Check that user profile exists in database
- Verify user_id is correct

## Future Enhancements

Potential AI improvements:
1. **Machine Learning** - Learn from user preferences
2. **Exercise Recommendations** - Suggest specific exercises based on equipment
3. **Progressive Overload** - Automatically increase weights/reps
4. **Injury Prevention** - Detect overtraining patterns
5. **Nutrition Integration** - Sync with meal plans
6. **Form Analysis** - Use computer vision to check exercise form

## Running in Production

For production deployment:

1. **Use Environment Variables**:
```python
import os
DB_CONFIG = {
    'host': os.getenv('DB_HOST', '127.0.0.1'),
    'user': os.getenv('DB_USER', 'root'),
    'password': os.getenv('DB_PASSWORD', ''),
    'database': os.getenv('DB_NAME', 'fitrova_db')
}
```

2. **Use Production Server**:
```bash
pip install gunicorn
gunicorn -w 4 -b 0.0.0.0:5001 recommend-workout:app
```

3. **Enable HTTPS** for secure API calls

4. **Add Authentication** to protect endpoints

## Summary

The AI workout system provides intelligent, personalized workout recommendations that adapt to each user's fitness level and goals. It automatically handles missed workouts and tracks progress, making it easy for users to stay consistent with their fitness journey.
