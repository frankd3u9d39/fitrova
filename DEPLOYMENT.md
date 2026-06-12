# Fitrova Deployment Guide

## 📋 Prerequisites

### System Requirements
- Docker 20.10+
- Docker Compose 2.0+
- Git
- 4GB RAM minimum (8GB recommended)

### Required Accounts
1. **Google Cloud Console** - For YouTube API key
2. **Paystack Account** - For payment processing
3. **SMTP Service** (SendGrid, Mailgun, or Gmail) - For email verification
4. **Domain Name** - For production deployment

---

## 🚀 Quick Start (Development)

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

# Edit configuration files
nano frontend/.env.development
nano backend/.env.local
```

### 3. Start Services
```bash
# Make deployment script executable
chmod +x deploy.sh

# Deploy development environment
./deploy.sh development
```

### 4. Access the Application
- **Frontend**: http://localhost:19006 (Expo Web)
- **Backend API**: http://localhost:8082
- **AI Service**: http://localhost:5001
- **Database Admin**: http://localhost:8080

---

## 🔧 Environment Configuration

### Frontend (.env.development)
```env
EXPO_PUBLIC_ENV=development
EXPO_PUBLIC_API_BASE_URL=http://localhost:8082/Fitrova/backend
EXPO_PUBLIC_YOUTUBE_API_KEY=your_youtube_api_key
EXPO_PUBLIC_AI_SERVICE_URL=http://localhost:5001
EXPO_PUBLIC_GOOGLE_CLIENT_ID=your_google_oauth_client_id
EXPO_PUBLIC_PAYSTACK_PUBLIC_KEY=pk_test_your_key
```

### Backend (.env.local)
```env
SMTP_USER=your_smtp_user@example.com
SMTP_PASS=your_smtp_password
YOUTUBE_API_KEY=your_youtube_api_key
PAYSTACK_SECRET_KEY=sk_test_your_secret_key
AI_SERVICE_URL=http://localhost:5001/api/analyze-youtube
DB_HOST=localhost
DB_NAME=fitrova_db
DB_USER=root
DB_PASS=
```

---

## 🌐 Production Deployment

### 1. Prepare Production Environment
```bash
# Create production environment files
cp frontend/.env.example frontend/.env.production
cp backend/.env.example backend/.env.production

# Edit with production values
nano frontend/.env.production
nano backend/.env.production
```

### 2. Production Configuration Checklist
- [ ] Use HTTPS URLs everywhere
- [ ] Set `EXPO_PUBLIC_ENV=production`
- [ ] Disable debug mode (`EXPO_PUBLIC_DEBUG=false`)
- [ ] Configure production database credentials
- [ ] Set up SSL certificates
- [ ] Configure backup strategies
- [ ] Set up monitoring (Sentry, etc.)

### 3. Deploy to Production
```bash
# Deploy production environment
./deploy.sh production
```

---

## 🐳 Docker Configuration

### Service Architecture
```
fitrova/
├── frontend/          # React Native Expo app
├── backend/           # PHP REST API
├── ai-service/        # Python Flask AI service
├── database/          # MySQL schemas & migrations
├── docker/            # Docker configuration
└── deploy.sh          # Deployment script
```

### Available Services
1. **mysql** - MySQL 8.0 database
2. **backend** - PHP/Apache REST API
3. **ai-service** - Python Flask AI service
4. **nginx** - Reverse proxy (optional)
5. **adminer** - Database management UI

### Customizing Docker
Edit `docker/docker-compose.yml` to:
- Change port mappings
- Add volume mounts
- Configure resource limits
- Enable GPU support for AI service

---

## 🔐 Security Configuration

### Critical Security Steps

1. **Change Default Passwords**
```bash
# Generate secure passwords
openssl rand -base64 32
```

2. **Configure SSL/TLS**
```bash
# Generate self-signed cert for development
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout docker/nginx/ssl/private.key \
  -out docker/nginx/ssl/certificate.crt
```

3. **Set Up Firewall Rules**
```bash
# Allow only necessary ports
ufw allow 80/tcp    # HTTP
ufw allow 443/tcp   # HTTPS
ufw allow 22/tcp    # SSH
ufw --force enable
```

4. **Configure Database Security**
- Use strong passwords
- Limit database user privileges
- Enable connection encryption
- Regular backups

---

## 📊 Monitoring & Maintenance

### Health Checks
```bash
# Check service status
docker-compose ps

# View logs
docker-compose logs -f backend
docker-compose logs -f ai-service

# Health check endpoints
curl http://localhost:8082/health
curl http://localhost:5001/health
```

### Backup Strategy
```bash
# Database backup script
docker exec fitrova_mysql mysqldump -u root -p fitrova_db > backup_$(date +%Y%m%d).sql

# Volume backups
docker run --rm -v fitrova_mysql_data:/data -v $(pwd):/backup alpine \
  tar czf /backup/mysql_backup_$(date +%Y%m%d).tar.gz /data
```

### Performance Monitoring
```bash
# Resource usage
docker stats

# Log monitoring
docker-compose logs --tail=100 backend
```

---

## 🚨 Troubleshooting

### Common Issues

#### 1. "Connection refused" errors
```bash
# Check if services are running
docker-compose ps

# Check logs for errors
docker-compose logs backend
```

#### 2. Database connection issues
```bash
# Test database connection
docker exec fitrova_mysql mysql -u root -p -e "SHOW DATABASES;"

# Check database logs
docker-compose logs mysql
```

#### 3. AI Service fails to start
```bash
# Check Python dependencies
docker exec fitrova_ai_service python --version

# Check service logs
docker-compose logs ai-service
```

#### 4. Frontend can't connect to backend
```bash
# Verify network
docker network ls
docker network inspect fitrova_fitrova_network

# Test API endpoint
curl http://localhost:8082/health
```

### Debug Mode
```bash
# Enable verbose logging
export APP_DEBUG=true
export FLASK_ENV=development
./deploy.sh development
```

---

## 📈 Scaling for Production

### Horizontal Scaling
```yaml
# In docker-compose.yml
services:
  backend:
    deploy:
      replicas: 3
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:80/health"]
      
  ai-service:
    deploy:
      replicas: 2
    resources:
      reservations:
        devices:
          - driver: nvidia
            count: 1
            capabilities: [gpu]
```

### Load Balancer Setup
```nginx
# nginx/load-balancer.conf
upstream backend {
    least_conn;
    server backend1:80 max_fails=3 fail_timeout=30s;
    server backend2:80 max_fails=3 fail_timeout=30s;
    server backend3:80 max_fails=3 fail_timeout=30s;
}

upstream ai-service {
    least_conn;
    server ai-service1:5001 max_fails=3 fail_timeout=30s;
    server ai-service2:5001 max_fails=3 fail_timeout=30s;
}
```

---

## 🔄 CI/CD Pipeline (Optional)

### GitHub Actions Example
```yaml
# .github/workflows/deploy.yml
name: Deploy to Production

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Deploy to Production
        env:
          SSH_PRIVATE_KEY: ${{ secrets.SSH_PRIVATE_KEY }}
          HOST: ${{ secrets.PRODUCTION_HOST }}
        run: |
          echo "$SSH_PRIVATE_KEY" > key.pem
          chmod 600 key.pem
          ssh -i key.pem $HOST "cd /opt/fitrova && git pull && ./deploy.sh production"
```

---

## 📝 Support & Maintenance

### Regular Tasks
- [ ] **Daily**: Check logs for errors
- [ ] **Weekly**: Database backups
- [ ] **Monthly**: Update dependencies
- [ ] **Quarterly**: Security audit

### Contact
For support, contact the development team or check the repository issues.

---

## ✅ Final Checklist Before Production Launch

### Infrastructure
- [ ] All services running in Docker
- [ ] HTTPS configured
- [ ] Domain name pointing to server
- [ ] SSL certificate installed
- [ ] Firewall configured
- [ ] Backup system in place

### Application
- [ ] Environment variables configured
- [ ] API keys secured
- [ ] Database seeded with initial data
- [ ] Error tracking configured
- [ ] Performance monitoring enabled

### Testing
- [ ] All API endpoints tested
- [ ] Mobile apps built and tested
- [ ] Load testing completed
- [ ] Security audit passed

### Documentation
- [ ] Deployment guide complete
- [ ] API documentation updated
- [ ] Emergency procedures documented
- [ ] Contact information available