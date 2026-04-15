# Quick Start - Dashboard Setup

## 3 Simple Steps to Get Dashboard Working

### 1️⃣ Run Database Migration (2 minutes)

Open phpMyAdmin: http://localhost/phpmyadmin

**Use this file**: `backend/migrate_dashboard_simple.sql`

1. Select `fitrova_db` database
2. Click "SQL" tab
3. Copy entire contents of `backend/migrate_dashboard_simple.sql`
4. Paste and click "Go"
5. ✅ Ignore any "duplicate" warnings - they're safe!

This creates 5 new tables and adds sample data for testing.

### 2️⃣ Verify XAMPP is Running

- ✅ Apache server running
- ✅ MySQL server running
- ✅ Database `fitrova_db` exists

### 3️⃣ Test the App

```bash
cd frontend
npm start
```

Login with any user (or user ID 1 to see sample data).

## What You'll See

The dashboard now displays:
- **Health Score**: Calculated from your activity (50-100)
- **Calories**: Today's consumption vs daily goal
- **Weight Trend**: Last 7 days chart
- **Today's Workout**: Current workout plan
- **AI Insight**: Motivational tips

## All Data is Real

✅ No mock data
✅ Fetched from database
✅ Updates in real-time
✅ TypeScript errors fixed

## Files Changed

- `frontend/src/screens/home/HomeDashboardScreen/DashboardScreen.tsx` - Fixed weight parsing
- `frontend/src/navigation/AppNavigator.tsx` - Added userId to navigation
- `frontend/src/screens/onboarding/LoginScreen/LoginScreen.tsx` - Pass userId on login
- `backend/get_dashboard_data.php` - Enhanced error handling
- `backend/migrate_dashboard_tables.sql` - NEW: Database migration

## Need Help?

See `backend/README_DASHBOARD_SETUP.md` for detailed instructions.
See `DASHBOARD_INTEGRATION_COMPLETE.md` for full technical details.
