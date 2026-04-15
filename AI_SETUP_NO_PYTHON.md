# 🤖 AI Workout System - No Python Required!

## Overview

Instead of Python, we're using **AI APIs directly from PHP**. Choose one:

1. **Google Gemini** (FREE, Recommended)
2. **OpenAI ChatGPT** (Paid, $0.002 per request)

## Option 1: Google Gemini (FREE) ⭐

### Step 1: Get API Key

1. Go to https://makersuite.google.com/app/apikey
2. Click "Create API Key"
3. Copy your API key

### Step 2: Add API Key to PHP

Open `backend/ai_workout_gemini.php` and update line 13:

```php
$GEMINI_API_KEY = 'YOUR_ACTUAL_API_KEY_HERE';
```

### Step 3: Test It

Open in browser:
```
http://YOUR_IP/Fitrova/backend/ai_workout_gemini.php
```

POST with body:
```json
{"user_id": 1}
```

Should return AI-generated workout plan!

### Step 4: Use in App

The app is already configured to use Gemini. Just restart your app and it will work!

## Option 2: OpenAI ChatGPT (Paid)

### Step 1: Get API Key

1. Go to https://platform.openai.com/api-keys
2. Click "Create new secret key"
3. Copy your API key
4. Add $5-10 credit to your account

### Step 2: Add API Key to PHP

Open `backend/ai_workout_openai.php` and update line 13:

```php
$OPENAI_API_KEY = 'sk-YOUR_ACTUAL_API_KEY_HERE';
```

### Step 3: Update Frontend

In `frontend/src/services/api/workoutService.ts`, change line 109:

```typescript
const fetchPromise = fetch(`${AI_SERVICE_URL}/ai_workout_openai.php`, {
```

### Step 4: Test It

Same as Gemini, but use `ai_workout_openai.php` endpoint.

## Comparison

| Feature | Google Gemini | OpenAI ChatGPT |
|---------|--------------|----------------|
| Cost | FREE | ~$0.002/request |
| Speed | Fast | Very Fast |
| Quality | Excellent | Excellent |
| Setup | Easy | Easy |
| Limits | 60 requests/min | Based on tier |

## How It Works

### Without Python:

```
User opens Workout tab
    ↓
Frontend calls PHP endpoint
    ↓
PHP reads user profile from database
    ↓
PHP builds AI prompt with user data
    ↓
PHP calls Gemini/OpenAI API
    ↓
AI generates personalized workout
    ↓
PHP returns workout to frontend
    ↓
User sees AI-generated workout!
```

### Benefits:

✅ No Python installation needed
✅ No separate service to run
✅ Works with existing XAMPP setup
✅ Uses same backend as rest of app
✅ Real AI personalization
✅ Automatic fallback if API fails

## What the AI Generates

Based on user profile, the AI creates:

- **Today's Workout**: Personalized exercises, duration, difficulty
- **Upcoming Workouts**: Next 2 workouts in the plan
- **Recovery Score**: Based on workout history
- **Fitness Level**: Beginner/Intermediate/Advanced
- **Weekly Progress**: Tracks completion

## Example AI Prompt

```
You are a professional fitness trainer. Generate a personalized workout plan.

User Profile:
- Name: John
- Fitness Goal: muscle gain
- Activity Level: moderate
- Workouts in last 30 days: 8
- Days since last workout: 2
- Age: 25

Generate a workout plan and return ONLY valid JSON...
```

## Example AI Response

```json
{
  "todays_workout": {
    "name": "Upper Body Hypertrophy",
    "exercises": [
      "Barbell Bench Press 4x8",
      "Incline Dumbbell Press 3x10",
      "Cable Flyes 3x12",
      "Overhead Press 4x8",
      "Lateral Raises 3x15"
    ],
    "exercises_count": 5,
    "duration": 50,
    "difficulty": "intermediate",
    "type": "strength"
  },
  "upcoming_workouts": [...],
  "recovery_score": 92,
  "status": "READY FOR SESSION",
  "fitness_level": "intermediate",
  "weekly_progress": {"completed": 2, "goal": 4}
}
```

## Troubleshooting

### "Invalid API key"
- Check you copied the full API key
- For OpenAI, make sure it starts with `sk-`
- For Gemini, regenerate key if needed

### "Quota exceeded"
- Gemini: Wait 1 minute (60 requests/min limit)
- OpenAI: Add more credits to your account

### "Failed to parse AI response"
- AI sometimes returns markdown
- PHP code already handles this
- Check error message for details

### Still using fallback data
- Check API key is correct
- Test endpoint directly in browser
- Check PHP error logs

## Cost Estimate

### Google Gemini:
- **FREE** for up to 60 requests per minute
- Perfect for development and production

### OpenAI ChatGPT:
- ~$0.002 per workout generation
- 1000 users × 1 workout/day = $2/day = $60/month
- Still very affordable!

## Files Created

1. `backend/ai_workout_gemini.php` - Gemini AI integration
2. `backend/ai_workout_openai.php` - OpenAI integration
3. `frontend/src/services/api/workoutService.ts` - Updated to use PHP
4. `AI_SETUP_NO_PYTHON.md` - This guide

## Summary

✅ **No Python needed** - Everything runs in PHP
✅ **Real AI** - Google Gemini or OpenAI
✅ **Free option** - Gemini is completely free
✅ **Easy setup** - Just add API key
✅ **Automatic fallback** - Works even if API fails
✅ **Personalized workouts** - Based on user profile

Choose Gemini (free) or OpenAI (paid), add your API key, and you're done!
