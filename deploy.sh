#!/bin/bash

# Fitrova Deployment Script
# Usage: ./deploy.sh [environment]

set -e

ENVIRONMENT=${1:-staging}
COMPOSE_FILE="docker/docker-compose.yml"
ENV_FILE=".env.${ENVIRONMENT}"

echo "🚀 Deploying Fitrova to ${ENVIRONMENT} environment..."

# Check if environment file exists
if [ ! -f "${ENV_FILE}" ]; then
    echo "❌ Environment file ${ENV_FILE} not found"
    echo "Available environments: development, staging, production"
    exit 1
fi

echo "📋 Loading environment variables from ${ENV_FILE}"
export $(grep -v '^#' ${ENV_FILE} | xargs)

# Build and deploy based on environment
case ${ENVIRONMENT} in
    development)
        echo "🔨 Building development environment..."
        docker-compose -f ${COMPOSE_FILE} down
        docker-compose -f ${COMPOSE_FILE} build --no-cache
        docker-compose -f ${COMPOSE_FILE} up -d
        ;;
        
    staging|production)
        echo "🏗️ Building ${ENVIRONMENT} environment..."
        
        # Pull latest images
        docker-compose -f ${COMPOSE_FILE} pull
        
        # Build with no cache for production
        docker-compose -f ${COMPOSE_FILE} build --no-cache
        
        # Run database migrations
        echo "🗄️ Running database migrations..."
        docker-compose -f ${COMPOSE_FILE} run --rm backend php scripts/run_migration.php
        
        # Deploy services
        echo "🚀 Starting services..."
        docker-compose -f ${COMPOSE_FILE} up -d
        
        # Scale services for production (optional)
        if [ "${ENVIRONMENT}" = "production" ]; then
            echo "⚖️ Scaling services for production..."
            docker-compose -f ${COMPOSE_FILE} up -d --scale backend=3 --scale ai-service=2
        fi
        ;;
        
    *)
        echo "❌ Unknown environment: ${ENVIRONMENT}"
        exit 1
        ;;
esac

echo "✅ Deployment completed!"
echo ""
echo "📊 Services status:"
docker-compose -f ${COMPOSE_FILE} ps

echo ""
echo "🌐 Access points:"
echo "  Backend API:    http://localhost:8082"
echo "  AI Service:     http://localhost:5001"
echo "  Database Admin: http://localhost:8080"
echo ""
echo "📝 Logs:"
echo "  Backend logs:   docker-compose -f ${COMPOSE_FILE} logs backend"
echo "  AI logs:        docker-compose -f ${COMPOSE_FILE} logs ai-service"
echo "  All logs:       docker-compose -f ${COMPOSE_FILE} logs -f"