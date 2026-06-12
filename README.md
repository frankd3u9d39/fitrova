# 🏋️ Fitrova - AI-Powered Fitness Application

Fitrova is a comprehensive fitness application with AI-powered workout recommendations, nutrition tracking, and form analysis.

## ✨ Features

### 🏃‍♂️ Workout Features
- **AI-powered workout recommendations** based on fitness level, goals, and history
- **Personalized workout plans** with automatic scheduling
- **Form check** with camera-based analysis using MediaPipe
- **YouTube workout analysis** - upload videos for AI form evaluation
- **Progress tracking** with achievements and personal records
- **Recovery score** calculation and workout optimization

### 🥗 Nutrition Features
- **Meal logging** with macro tracking (protein, carbs, fats)
- **AI food recommendations** based on goals and preferences
- **Food scanning** with camera integration
- **Calorie tracking** with daily goals
- **Nutrition insights** and tips from AI

### 👤 User Management
- **Multi-step onboarding** with personalization
- **Weight tracking** with trend visualization
- **Achievements system** with badges
- **Profile customization** with avatars and bios
- **Notification system** for reminders and insights

### 💳 Subscription System
- **Free tier** - Basic features
- **Premium tier** - Advanced AI recommendations
- **Advanced Premium** - Full feature access
- **Paystack integration** for payments

## 🏗️ Architecture

### Technology Stack
- **Frontend**: React Native with Expo (iOS/Android/Web)
- **Backend**: PHP 8.1 with MySQL (REST API)
- **AI Service**: Python Flask with MediaPipe, OpenCV, Gemini API
- **Database**: MySQL 8.0
- **Infrastructure**: Docker with Docker Compose

### Project Structure
```
fitrova/
├── frontend/                 # React Native Expo app
│   ├── src/
│   │   ├── screens/         # All application screens
│   │   ├── components/      # Reusable UI components
│   │   ├── navigation/      # Navigation configuration
│   │   ├── services/        # API clients and services
│   │   ├── config/          # Environment configuration
│   │   └── utils/           # Utility functions
│   ├── .env.development     # Development environment
│   ├── .env.staging         # Staging environment
│   └── .env.production      # Production environment
│
├── backend/                  # PHP REST API
│   ├── app/controllers/     # API controllers
│   │   └── api/v1/          # Versioned API structure
│   ├── config/              # Configuration files
│   ├── public/              # Public files (index.php)
│   ├── storage/             # Logs and uploads
│   ├── .env.local           # Local configuration
│   └── Dockerfile           # Docker configuration
│
├── ai-service/              # Python AI service
│   ├── api/                 # Flask API endpoints
│   ├── model/               # ML models
│   ├── utils/               # Utility functions
│   ├── requirements.txt     # Python dependencies
│   └── Dockerfile           # Docker configuration
│
├── database/                # Database schemas
│   ├── migrations/          # Database migrations
│   ├── schema.sql          # Main database schema
│   └── init.sql            # Database initialization
│
├── docker/                  # Docker orchestration
│   ├── docker-compose.yml   # Multi-service setup
│   └── nginx/              # Reverse proxy config
│
└── docs/                    # Documentation
```

## 🚀 Quick Start

### Prerequisites
- Docker 20.10+
- Docker Compose 2.0+
- Node.js 18+ (for development)
- Python 3.10+ (for AI service development)

### 1. Clone and Setup
```bash
git clone <repository-url>
cd fitrova
```

### 2. Configure Environment
```bash
# Copy environment templates
cp frontend/.env.example frontend/.env.development
cp backend/.env.example backend/.env.local

# Edit with your configuration
nano frontend/.env.development
nano backend/.env.local
```

### 3. Start Services
```bash
# Make deployment script executable
chmod +x deploy.sh

# Start development environment
./deploy.sh development
```

### 4. Access Services
- **Frontend**: http://localhost:19006 (Expo Web)
- **Backend API**: http://localhost:8082
- **AI Service**: http://localhost:5001
- **Database Admin**: http://localhost:8080

## 📦 API Documentation

### Base URL
```
Development: http://localhost:8082/Fitrova/backend
Staging: https://staging-api.fitrova.com/backend
Production: https://api.fitrova.com/backend
```

### Versioning
API is versioned: `/api/v1/endpoint`

### Authentication
Most endpoints require authentication via session or JWT token.

### Endpoints
See `frontend/src/services/api/apiClient.ts` for complete endpoint list.

## 🔧 Development

### Frontend Development
```bash
cd frontend
npm install
npm start
```

### Backend Development
```bash
cd backend
# Start with Docker
docker-compose up backend
```

### AI Service Development
```bash
cd ai-service
python -m venv venv
source venv/bin/activate  # On Mac/Linux
# venv\Scripts\activate   # On Windows
pip install -r requirements.txt
python app.py
```

## 🐳 Docker Services

### Available Services
```yaml
mysql:          # MySQL 8.0 database
backend:        # PHP REST API
ai-service:     # Python AI service
nginx:          # Reverse proxy (optional)
adminer:        # Database management UI
```

### Docker Commands
```bash
# Start all services
docker-compose up -d

# Stop all services
docker-compose down

# View logs
docker-compose logs -f
docker-compose logs backend
docker-compose logs ai-service

# Access containers
docker exec -it fitrova_backend bash
docker exec -it fitrova_mysql mysql -u root -p
```

## 🔐 Security Features

### Implemented
- ✅ Input sanitization and validation
- ✅ SQL injection prevention (PDO prepared statements)
- ✅ XSS protection headers
- ✅ CSRF protection
- ✅ Rate limiting
- ✅ Secure password hashing (bcrypt)
- ✅ Environment-based configuration

### To Configure
- SSL/TLS certificates
- WAF (Web Application Firewall)
- API key rotation
- Database encryption
- Backup encryption

## 📊 Monitoring

### Health Checks
```bash
# API health
curl http://localhost:8082/health

# AI service health
curl http://localhost:5001/health

# Database health
docker exec fitrova_mysql mysqladmin ping
```

### Logging
- Application logs in `backend/storage/logs/`
- AI service logs in `ai-service/logs/`
- Docker logs via `docker-compose logs`

## 🚀 Production Deployment

### 1. Configure Production Environment
```bash
cp frontend/.env.example frontend/.env.production
cp backend/.env.example backend/.env.production
# Edit with production values
```

### 2. Deploy to Production
```bash
./deploy.sh production
```

### 3. Production Checklist
- [ ] SSL certificates configured
- [ ] Domain name pointing to server
- [ ] Database backups configured
- [ ] Monitoring tools set up
- [ ] Error tracking (Sentry) configured
- [ ] Load testing completed

## 📝 Documentation

### Additional Documentation
- [DEPLOYMENT.md](DEPLOYMENT.md) - Complete deployment guide
- [docs/](docs/) - Architecture and API documentation
- [backend/config/](backend/config/) - Configuration examples
- [database/](database/) - Database schema documentation

### API Reference
See `docs/api-documentation.md` for detailed API documentation.

## 🤝 Contributing

### Development Workflow
1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit changes (`git commit -m 'Add amazing feature'`)
4. Push to branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Coding Standards
- Frontend: TypeScript with React Native best practices
- Backend: PSR-12 PHP coding standards
- Python: PEP 8 style guide
- Commit messages: Conventional Commits

## 📄 License

This project is proprietary software. All rights reserved.

## 🆘 Support

For support, please:
1. Check the [documentation](docs/)
2. Look at existing [issues](../../issues)
3. Contact the development team

## 🎯 Roadmap

### Phase 1 (Complete) ✅
- ✅ User authentication and onboarding
- ✅ Basic workout and nutrition tracking
- ✅ AI workout recommendations
- ✅ Payment integration

### Phase 2 (In Progress) 🔄
- 🔄 Advanced form analysis with pose detection
- 🔄 Social features (friends, challenges)
- 🔄 Gamification (leaderboards, challenges)
- 🔄 Advanced analytics and reporting

### Phase 3 (Planned) 📋
- 📋 Integration with Apple HealthKit/Google Fit
- 📋 Offline mode support
- 📋 Multi-language support
- 📋 Advanced AI coaching

---

**Built with ❤️ by the Fitrova Team**