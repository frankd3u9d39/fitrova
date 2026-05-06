# ⚡ Run This Migration - Step by Step

## You Got That Error Because...

The `survey_step` column already exists in your database (from `backend/update_schema.sql`). That's fine! We just need to add the NEW tables for the dashboard.

## ✅ Simple Solution - Run This File

Use this file: **`backend/migrate_dashboard_simple.sql`**

This file:
- ✅ Only creates NEW tables (skips if they exist)
- ✅ Won't try to add columns that already exist
- ✅ Includes sample data for testing
- ✅ Safe to run multiple times

## 📋 Steps to Run

### Option 1: phpMyAdmin (Easiest)

1. Open phpMyAdmin: http://localhost/phpmyadmin
2. Click on `fitrova_db` database in left sidebar
3. Click "SQL" tab at the top
4. Open this file: `backend/migrate_dashboard_simple.sql`
5. Copy ALL the contents
6. Paste into the SQL box
7. Click "Go" button
8. ✅ Done! (Ignore any "Duplicate" warnings)

### Option 2: Command Line

```bash
cd C:\xampp\mysql\bin
mysql -u root fitrova_db < C:\xampp\htdocs\Fitrova\backend\migrate_dashboard_simple.sql
```

## 🔍 Verify It Worked

After running the migration, check that these tables exist:

1. In phpMyAdmin, select `fitrova_db`
2. You should see these NEW tables:
   - ✅ `weight_history`
   - ✅ `nutrition_logs`
   - ✅ `workout_plans`
   - ✅ `workout_logs`
   - ✅ `ai_insights`

3. Click on each table to see the sample data

## 🎯 Test the Dashboard

1. Make sure XAMPP is running (Apache + MySQL)
2. Start your app: `cd frontend && npm start`
3. Login with user ID 1 (or any user in your database)
4. Go to Home tab
5. You should see:
   - Health score: 75
   - Calories: 1000 / 2000
   - Weight chart with 7 days of data
   - Today's workout: "Upper Body Strength"
   - AI insight message

## ❌ If You See Errors

### "Table already exists"
- ✅ This is SAFE - ignore it
- The table was already created, script continues

### "Duplicate entry"
- ✅ This is SAFE - ignore it
- Sample data was already inserted, script continues

### "Cannot add foreign key constraint"
- ❌ This means the `users` table doesn't exist
- Run `backend/setup.sql` first to create the users table

### Dashboard shows "Failed to load data"
- Check that MySQL is running in XAMPP
- Verify API endpoint: http://10.157.136.216/Fitrova/backend/get_dashboard_data.php
- Check browser console for errors

## 📝 What This Migration Does

Creates 5 new tables:
1. **weight_history** - Tracks weight over time (7 sample entries)
2. **nutrition_logs** - Records meals and calories (3 sample meals)
3. **workout_logs** - Logs completed workouts (1 sample workout)
4. **workout_plans** - Stores workout programs (empty)
5. **ai_insights** - AI tips and motivation (1 sample insight)

Adds sample data for user_id = 1 so you can test immediately.

## 🚀 After Migration

Your dashboard will display:
- Real data from database (no mock data)
- Health score calculated from activity
- Actual calories logged today
- Weight trend from last 7 days
- Today's workout if logged
- Latest AI insight

## Need Help?

If you encounter issues:
1. Check that XAMPP MySQL is running
2. Verify `fitrova_db` database exists
3. Check that `users` table has at least one user
4. Look at browser console for API errors
5. Check the response from: http://10.157.136.216/Fitrova/backend/get_dashboard_data.php

---

**TL;DR**: Run `backend/migrate_dashboard_simple.sql` in phpMyAdmin SQL tab. Ignore any "duplicate" warnings. Test dashboard.
