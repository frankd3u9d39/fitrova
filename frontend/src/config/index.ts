// Environment configuration for different deployment environments
import Constants from 'expo-constants';

/**
 * Derives the LAN host from Expo's Metro bundler URI.
 * In development, `Constants.expoConfig.hostUri` is something like "10.45.232.59:8081".
 * We extract just the IP so we can reach XAMPP from any physical device or emulator
 * without ever hardcoding `localhost` (which resolves to the device, not the PC).
 */
const getDevHost = (): string => {
  if (__DEV__) {
    const hostUri = Constants.expoConfig?.hostUri ?? Constants.manifest?.debuggerHost ?? '';
    if (hostUri) {
      // Strip the port — we only want the IP for XAMPP (port 80)
      return hostUri.split(':')[0];
    }
  }
  return 'localhost';
};

const DEV_HOST = getDevHost();
export type Environment = 'development' | 'staging' | 'production';

export interface AppConfig {
  env: Environment;
  apiBaseUrl: string;
  youtubeApiKey: string;
  aiServiceUrl: string;
  googleClientId: string;
  paystackPublicKey: string;
  loggingEnabled: boolean;
}

// Development configuration (default)
// Falls back to the auto-detected LAN IP so physical devices always resolve to the dev machine
const development: AppConfig = {
  env: 'development',
  apiBaseUrl: process.env.EXPO_PUBLIC_API_BASE_URL || `http://${DEV_HOST}/Fitrova/backend`,
  youtubeApiKey: process.env.EXPO_PUBLIC_YOUTUBE_API_KEY || 'YOUR_YOUTUBE_API_KEY_HERE',
  aiServiceUrl: process.env.EXPO_PUBLIC_AI_SERVICE_URL || `http://${DEV_HOST}:5001`,
  googleClientId: process.env.EXPO_PUBLIC_GOOGLE_CLIENT_ID || 'YOUR_GOOGLE_CLIENT_ID',
  paystackPublicKey: process.env.EXPO_PUBLIC_PAYSTACK_PUBLIC_KEY || 'pk_test_YOUR_KEY',
  loggingEnabled: true,
};

// Staging configuration
const staging: AppConfig = {
  env: 'staging',
  apiBaseUrl: process.env.EXPO_PUBLIC_API_BASE_URL || 'https://staging-api.fitrova.com/backend',
  youtubeApiKey: process.env.EXPO_PUBLIC_YOUTUBE_API_KEY || '',
  aiServiceUrl: process.env.EXPO_PUBLIC_AI_SERVICE_URL || 'https://staging-ai.fitrova.com',
  googleClientId: process.env.EXPO_PUBLIC_GOOGLE_CLIENT_ID || '',
  paystackPublicKey: process.env.EXPO_PUBLIC_PAYSTACK_PUBLIC_KEY || 'pk_test_YOUR_KEY',
  loggingEnabled: true,
};

// Production configuration
const production: AppConfig = {
  env: 'production',
  apiBaseUrl: process.env.EXPO_PUBLIC_API_BASE_URL || 'https://fitrova-backend.onrender.com',
  youtubeApiKey: process.env.EXPO_PUBLIC_YOUTUBE_API_KEY || '',
  aiServiceUrl: process.env.EXPO_PUBLIC_AI_SERVICE_URL || 'https://ibeh12-fitrova-ai.hf.space',
  googleClientId: process.env.EXPO_PUBLIC_GOOGLE_CLIENT_ID || '',
  paystackPublicKey: process.env.EXPO_PUBLIC_PAYSTACK_PUBLIC_KEY || 'pk_live_YOUR_KEY',
  loggingEnabled: false,
};

// Determine current environment based on EXPO_PUBLIC_ENV or NODE_ENV
const getEnvironment = (): Environment => {
  const env = process.env.EXPO_PUBLIC_ENV || process.env.NODE_ENV || 'development';
  
  switch (env.toLowerCase()) {
    case 'production':
      return 'production';
    case 'staging':
      return 'staging';
    default:
      return 'development';
  }
};

// Export configuration based on current environment
export const config = ((): AppConfig => {
  const env = getEnvironment();
  
  switch (env) {
    case 'production':
      return production;
    case 'staging':
      return staging;
    default:
      return development;
  }
})();

// Utility function to check if we're in development
export const isDevelopment = config.env === 'development';
export const isStaging = config.env === 'staging';
export const isProduction = config.env === 'production';

// Environment variable validation
export const validateEnvironment = (): string[] => {
  const warnings: string[] = [];

  if (config.youtubeApiKey === 'YOUR_YOUTUBE_API_KEY_HERE') {
    warnings.push('YouTube API key not configured');
  }

  if (config.aiServiceUrl.includes('localhost') && isProduction) {
    warnings.push('AI service URL points to localhost in production');
  }

  if (config.apiBaseUrl.includes('localhost') && isProduction) {
    warnings.push('API base URL points to localhost in production');
  }

  if (config.paystackPublicKey.includes('YOUR_KEY')) {
    warnings.push('Paystack API key not configured');
  }

  return warnings;
};