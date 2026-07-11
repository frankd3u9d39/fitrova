# 🏋️ Fitrova - AI-Powered Fitness Application

[![Tech Stack](https://img.shields.io/badge/Stack-React%20Native%20%7C%20PHP%20%7C%20Python-emerald.svg)](#technology-stack)
[![Database](https://img.shields.io/badge/Database-MySQL-blue.svg)](#database-schema)
[![AI Integration](https://img.shields.io/badge/AI-Gemini%20%7C%20OpenAI-purple.svg)](#-ai-engine--dual-modes)
[![License](https://img.shields.io/badge/License-Proprietary-red.svg)](#-license)

Fitrova is a premium, comprehensive fitness and health management ecosystem featuring AI-powered workout recommendation engines, nutrition logging, weight trend analytics, and real-time form checks. It provides a state-of-the-art interactive user experience with unified dark-mode styling, glassmorphism UI elements, and fluid micro-interactions.

---

## 📖 Table of Contents
- [✨ Key Features](#-key-features)
- [🤖 AI Engine & Dual Modes](#-ai-engine--dual-modes)
- [🏗️ System Architecture](#️-system-architecture)
- [🚀 Quick Start & Installation](#-quick-start--installation)
- [🗄️ Database & Dashboard Integration](#️-database--dashboard-integration)
- [📧 Email Verification Setup](#-email-verification-setup)
- [🔧 Development Workflow](#-development-workflow)
- [🐳 Docker Services Orchestration](#-docker-services-orchestration)
- [🔐 Security & Monitoring](#-security--monitoring)
- [🚀 Production Deployment Checklist](#-production-deployment-checklist)
- [🤝 Contributing](#-contributing)

---

## ✨ Key Features

### 🏃‍♂️ AI-Driven Workouts
* **Personalized Recommendations**: Dynamic exercise selection based on fitness level (Beginner/Intermediate/Advanced), primary goals (Muscle Gain, Strength, Cardio, Weight Loss), and workout frequency.
* **Auto-Rescheduling**: Intelligent detection of missed workouts, automatically rolling them forward to the next active day.
* **Recovery Score Analysis**: Dynamic calculation of physical recovery status (up to 98%) based on rest intervals and training volume.
* **Graceful Degradation (Fallback Mode)**: Seamless transition to high-quality local templates if the AI backend is unreachable—preventing app crashes.

### 🥗 Nutrition & Macro Tracker
* **Dashboard KPI Panel**: Circular progress indicator calculating your overall Health Score dynamically.
* **Calorie Tracking**: Real-time counter of calories consumed versus personalized daily goals.
* **Macro breakdown**: Visual summary logs of Proteins, Carbohydrates, and Fats.

### 👤 Profile, Trends & Onboarding
* **Multi-step Personalization Onboarding**: Initial health questionnaire mapping current metrics and goal settings.
* **Weight Trend Visualization**: A 7-day graphical weight tracking visualization to monitor progress.
* **Gamification System**: Custom achievement unlock triggers and badge cards.

### 💳 Tiered Subscriptions & Security
* **Flexible Tiers**: Free access (basic logging) and Premium plans (unlocked AI features).
* **Payment Gateway**: Secured payment transactions using [Paystack](https://paystack.com/) integration.
* **Real-time Email Verification**: Instant validation of signup emails using Abstract API to block disposable/fake mailboxes.

---

## 🤖 AI Engine & Dual Modes

The AI recommendation engine can run in **two modes**, allowing you to choose between running a local ML stack or going serverless.

| Feature | Option A: Dedicated Python AI Service | Option B: Native PHP Serverless AI (Recommended) |
| :--- | :--- | :--- |
| **Backend Engine** | Python Flask + MediaPipe + OpenCV | Native PHP script contacting APIs directly |
| **API Models** | Gemini API | Google Gemini (Free) OR OpenAI ChatGPT (Paid) |
| **Setup Cost** | Free (Local execution) | Free (Gemini) or ~$0.002 per prompt (OpenAI) |
| **Complexity** | Medium (Requires Python environment/libraries) | Low (Requires only a Gemini/OpenAI API key) |
| **Best For** | Heavy local CV & pose analysis | Lightweight, fast development & cloud deployment |

---

## 🏗️ System Architecture

### Technology Stack
* **Frontend Mobile App**: [React Native](https://reactnative.dev/) with [Expo](https://expo.dev/) (iOS, Android, and Web builds) using [Expo Vector Icons](https://icons.expo.fyi/) and [React Native Reanimated](https://docs.swmansion.com/react-native-reanimated/).
* **Core API Backend**: PHP 8.1+ RESTful API managing authentication, payments, data logging, and database operations.
* **AI Service (Optional)**: Python 3.10+ Flask application for specialized local computer-vision and pose analysis.
* **Database Layer**: MySQL 8.0 containing relational structures for users, profiles, weights, logs, and sub-systems.
* **Infrastructure**: Dockerized environment managed via Docker Compose.

### Directory Structure
```
fitrova/
├── frontend/                  # React Native Expo App
│   ├── src/
│   │   ├── screens/          # App views (onboarding, dashboard, workout, nutrition)
│   │   ├── components/       # Custom reusable UI units
│   │   ├── services/         # API clients (workoutService.ts, emailVerification.ts)
│   │   ├── config/           # Environment setup files
│   │   └── theme/            # Emerald color scheme definition
│   └── package.json
├── backend/                   # PHP Core REST API
│   ├── app/                  # Controllers, Models, Middleware, Services
│   ├── config/               # Database config, environment loader, security rules
│   ├── scripts/              # Migration executors
│   └── migrate_dashboard_simple.sql   # Simple DB structure and mock data script
├── ai-service/                # Python ML / CV Service
│   ├── api/                  # Flask endpoint scripts (recommend-workout.py)
│   └── requirements.txt      # Python dependencies
├── database/                  # Schema blueprints
│   ├── schema.sql            # Base schema definitions
│   └── init.sql              # Database initialization statements
├── docker/                    # Infrastructure Containerization
│   ├── docker-compose.yml    # Root multi-container manager
│   └── nginx/                # Reverse proxy config (SSL/load balancing)
└── deploy.sh                  # Automated multi-environment deployer script
```

---

## 🚀 Quick Start & Installation

### Prerequisites
* Docker & Docker Compose installed.
* Node.js v18+ (for development without Docker containers).
* PHP 8.1+ & MySQL (if running locally via XAMPP instead of Docker).
* An [Abstract API Key](https://www.abstractapi.com/api/email-verification-validation-api) for registration validation.

---

### Step 1: Environment Variables Setup
Clone the repository and copy the environment configuration templates:

```bash
# Clone the repository
git clone <repository-url> fitrova
cd fitrova

# Configure Frontend environment
cp frontend/.env.example frontend/.env.development

# Configure Backend environment
cp backend/.env.example backend/.env.local
```

Edit the environment files with your credentials (API keys, ports, etc.):
* **`frontend/.env.development`**
* **`backend/.env.local`**

---

### Step 2: Database Initialization & Migrations

For your dashboard, user profiles, and workout tracking to function, run the database migrations. You can do this in two ways:

#### Option A: phpMyAdmin (Recommended)
1. Open phpMyAdmin: [http://localhost/phpmyadmin](http://localhost/phpmyadmin)
2. Create or select the `fitrova_db` database.
3. Import the base database from [database/schema.sql](file:///c:/xampp/htdocs/Fitrova/database/schema.sql).
4. Select the `SQL` tab, copy the contents of [backend/migrate_dashboard_simple.sql](file:///c:/xampp/htdocs/Fitrova/backend/migrate_dashboard_simple.sql), paste them into the query editor, and click **Go**.

#### Option B: Command Line (Local MySQL)
```bash
# Navigate to XAMPP mysql binary path
cd C:\xampp\mysql\bin

# Execute the migrations
mysql -u root fitrova_db < C:\xampp\htdocs\Fitrova\backend\migrate_dashboard_simple.sql
```

---

### Step 3: Run the Application

#### Option A: Run Services Using Docker Compose (Recommended)
You can start all backend services automatically:

```bash
# Build and run containers from the root directory
docker-compose -f docker/docker-compose.yml up --build -d
```

#### Option B: Run Services Manually (XAMPP + Node.js)
1. **Start XAMPP Control Panel** and enable **Apache** & **MySQL**.
2. **Start the Frontend Application**:
   ```bash
   cd frontend
   npm install
   npm start
   ```
3. **Start the AI Service** (If using Option A Python Engine):
   ```bash
   cd ai-service
   pip install -r requirements.txt
   cd api
   python recommend-workout.py
   ```

---

## 🗄️ Database & Dashboard Integration

The dashboard reads from five core tables added in the latest migration:
1. **`weight_history`**: Tracks daily weight records for trend charts.
2. **`nutrition_logs`**: Logs user meals (Breakfast, Lunch, Dinner, Snacks) and macros.
3. **`workout_logs`**: Stores metrics on finished physical sessions.
4. **`workout_plans`**: Holds personalized calendar schedules.
5. **`ai_insights`**: Stores generated motivating tips.

### Health Score Calculation
The Health Score is computed dynamically in [backend/get_dashboard_data.php](file:///c:/xampp/htdocs/Fitrova/backend/get_dashboard_data.php):
* **Base Score**: 50 points
* **Activity Bonus**: +10 pts if calories are logged today
* **Workout Bonus**: +15 pts if a workout is completed today
* **Consistency Bonus**: +10 pts if $\ge 3$ weight records exist in the last 7 days
* **Maximum Score**: 100 points

---

## 📧 Email Verification Setup

Fitrova verifies email deliverability and checks for temporary address generators during registration.

1. Sign up for a free account at [Abstract API](https://www.abstractapi.com/).
2. Retrieve your unique **Email Verification API Key**.
3. Place this key inside [frontend/src/services/api/emailVerification.ts](file:///c:/xampp/htdocs/Fitrova/frontend/src/services/api/emailVerification.ts):
   ```typescript
   const ABSTRACT_API_KEY = 'YOUR_ABSTRACT_API_KEY';
   ```
4. Custom threshold restrictions (such as quality thresholds) can be adjusted inside [frontend/src/hooks/useEmailVerification.ts](file:///c:/xampp/htdocs/Fitrova/frontend/src/hooks/useEmailVerification.ts).

---

## 🔧 Development Workflow

### Frontend Controls
The mobile application uses Expo. In the `frontend` directory, the following scripts are available:
* `npm start`: Starts the Expo development server.
* `npm run android`: Runs the app in an Android device or emulator.
* `npm run ios`: Runs the app in an iOS simulator.
* `npm run web`: Opens the web rendering engine in your default browser.
* `npm run lint`: Verifies code syntax and lint guidelines.

### API Endpoints
* **Development Backend Base**: `http://localhost:8082/Fitrova/backend`
* **AI Service Base**: `http://localhost:5001`
* **Versioned API Structure**: `/api/v1/`

---

## 🐳 Docker Services Orchestration

Manage all backend elements inside isolated containers using the following commands (always executed from the project root):

```bash
# Start all containers in detached mode
docker-compose -f docker/docker-compose.yml up -d

# View live container logs
docker-compose -f docker/docker-compose.yml logs -f

# Specific container logs
docker-compose -f docker/docker-compose.yml logs backend
docker-compose -f docker/docker-compose.yml logs ai-service

# Access a container's shell terminal (e.g. backend service)
docker exec -it fitrova_backend bash

# Stop and remove containers
docker-compose -f docker/docker-compose.yml down
```

---

## 🔐 Security & Monitoring

### Implemented Security Layer
* **Prepared SQL Statements**: Standardized PDO querying prevents SQL Injections.
* **XSS Defense Headers**: Protection against cross-site scripting vulnerabilities.
* **Authentication**: Token-based sessions to prevent route injection.
* **Password Encryption**: All passwords are encrypted in the database using `bcrypt`.
* **Input Sanitization**: Built-in validation rules for all incoming HTTP requests.

### Maintenance & Health Diagnostics
Perform health status checks on running services using:
```bash
# Test backend health
curl http://localhost:8082/health

# Test AI service health
curl http://localhost:5001/health

# Test database status
docker exec fitrova_mysql mysqladmin ping
```

---

## 🚀 Production Deployment Checklist

Before going live with your production servers, verify the following:
- [ ] **SSL/TLS Certificates**: Configure HTTPS mappings in Nginx configuration.
- [ ] **Environment Context**: Update configurations and set `EXPO_PUBLIC_ENV=production`.
- [ ] **API Verification**: Update your frontend configurations to production URLs instead of `localhost`.
- [ ] **Paystack Live Keys**: Swap Paystack testing credentials for production keys.
- [ ] **Webhook Settings**: Configure Paystack Webhook settings on the Paystack dashboard to point to: `https://your-domain.com/app/controllers/payments/paystack_webhook.php`.
- [ ] **Database Backups**: Set up a crontab schedule for database backups:
  ```bash
  docker exec fitrova_mysql mysqldump -u root -p fitrova_db > backup_$(date +%Y%m%d).sql
  ```

---

## 🤝 Contributing

We follow standard git workflows for enhancements:
1. Create a feature branch: `git checkout -b feature/your-feature-name`
2. Commit your code modifications: `git commit -m "feat: implement your feature"`
3. Push the updates to your branch: `git push origin feature/your-feature-name`
4. Open a Pull Request for code review.

### Standards Checklist
* **Backend**: PSR-12 standard formatting.
* **Frontend**: TypeScript strict conventions.
* **Python**: PEP 8 style guide checks.
* **Commits**: Conventional Commits style guide.

---

## 📄 License

This software is **proprietary** and all rights are reserved.

---

**Developed with ❤️ by the Fitrova Team**