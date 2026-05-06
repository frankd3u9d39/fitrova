# Dashboard Database Setup

## Quick Setup Instructions

To enable the dashboard with real database data, you need to run the migration script that adds the necessary tables to your existing `fitrova_db` database.

### Step 1: Run the Migration

1. Open phpMyAdmin (http://localhost/phpmyadmin)
2. Select the `fitrova_db` database from the left sidebar
3. Click on the "SQL" tab at the top
4. Copy and paste the contents of `migrate_dashboard_tables.sql`
5. Click "Go" to execute the script

**OR** if you prefer command line:

```bash
# Navigate to your XAMPP mysql bin directory
cd C:\xampp\mysql\bin

# Run the migration
mysql -u root -p fitrova_db < C:\xampp\htdocs\Fitrova\backend\migrate_dashboard_tables.sql
```

### Step 2: Verify Tables Were Created

After running the migration, verify these tables exist in your database:
- `weight_history`
- `nutrition_logs`
- `workout_plans`
- `workout_logs`
- `ai_insights`

Also verify that `user_profiles` table has these new columns:
- `current_weight`
- `daily_calorie_goal`
- `health_score`

### Step 3: Test the Dashboard

1. Login to your app with an existing user
2. The dashboard should now display:
   - Health score (calculated from your activity)
   - Calories consumed vs goal
   - Weight trend chart (last 7 days)
   - Today's workout
   - AI insights

### Sample Data

The migration script includes sample data for user ID 1 to help you see the dashboard in action:
- 7 days of weight history
- Today's nutrition logs (breakfast, lunch, snack)
- Today's workout log
- An AI motivation insight

If you want to see this data, make sure you're logged in as the user with ID 1.

### Troubleshooting

**Problem**: Dashboard shows "Failed to load dashboard data"
- Check that your XAMPP MySQL server is running
- Verify the database connection in `backend/db_config.php`
- Check the browser console for error messages

**Problem**: Dashboard shows loading forever
- Check that the API endpoint is accessible: http://10.157.136.216/Fitrova/backend/get_dashboard_data.php
- Verify your IP address matches the one in `frontend/.env` (API_BASE_URL)
- Check the network tab in browser dev tools for failed requests

**Problem**: No data showing on dashboard
- The migration includes sample data for user ID 1
- If you're logged in as a different user, you'll need to add data manually or through the app
- You can insert test data by modifying the sample data section in the migration script

### API Endpoint

The dashboard fetches data from:
```
POST http://10.157.136.216/Fitrova/backend/get_dashboard_data.php
Body: { "user_id": 1 }
```

### Database Schema

The complete schema is documented in `database/schema.sql` for reference.
