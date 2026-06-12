import { config } from '../../config';

export const API_BASE_URL = config.apiBaseUrl;
export const API_VERSION = 'v1';

// Mapping of clean/versioned paths to actual PHP backend files
const MAPPED_ENDPOINTS: Record<string, string> = {
  'auth/register': 'app/controllers/auth/register.php',
  'auth/login': 'app/controllers/auth/login.php',
  'auth/google-auth': 'app/controllers/auth/google_auth.php',
  'auth/save-profile': 'app/controllers/auth/save_profile.php',
  'auth/check-email': 'app/controllers/auth/check_email.php',
  'auth/verify-email': 'app/controllers/auth/verify_email.php',
  'auth/send-code': 'app/controllers/auth/send_code.php',
  'auth/change-password': 'app/controllers/auth/change_password.php',
  'auth/forgot-password': 'app/controllers/auth/forgot_password.php',
  'workout/dashboard': 'app/controllers/workout/get_dashboard_data.php',
  'profile/achievements': 'app/controllers/profile/get_achievements.php',
  'nutrition/data': 'app/controllers/nutrition/get_nutrition_data.php',
  'nutrition/log': 'app/controllers/nutrition/log_meal.php',
  'nutrition/scan': 'app/controllers/nutrition/scan_meal.php',
  'nutrition/ai-recommendations': 'app/controllers/nutrition/ai_food_recommendations.php',
  'nutrition/dismiss-insight': 'app/controllers/nutrition/dismiss_insight.php',
  'profile/ai-recommendation': 'app/controllers/profile/get_ai_recommendation.php',
  'profile/update': 'app/controllers/profile/update_profile.php',
  'profile/preferences': 'app/controllers/profile/save_preferences.php',
  'profile/log-weight': 'app/controllers/profile/log_weight.php',
  'profile/notifications': 'app/controllers/profile/get_notifications.php',
  'profile/notifications/read': 'app/controllers/profile/mark_notification_read.php',
  'payment/initialize': 'app/controllers/payment/paystack_initialize.php',
  'payment/verify': 'app/controllers/payment/paystack_verify.php',
  'system/status': 'app/controllers/system/get_status.php',
};

// Build endpoint URL with versioning or legacy fallback mapping
const buildEndpoint = (path: string) => {
  // Remove leading and trailing slashes
  const cleanPath = path.replace(/^\/+|\/+$/g, '');
  
  // Support both versioned and legacy endpoints
  if (cleanPath.startsWith('app/controllers/')) {
    // Legacy endpoint (without version)
    const base = API_BASE_URL.replace(/\/+$/, ''); // Remove trailing slash
    return `${base}/${cleanPath}`;
  }
  
  // Directly map clean/versioned routes to their corresponding PHP file on the backend
  if (MAPPED_ENDPOINTS[cleanPath]) {
    const base = API_BASE_URL.replace(/\/+$/, '');
    return `${base}/${MAPPED_ENDPOINTS[cleanPath]}`;
  }
  
  // Versioned endpoint
  const base = API_BASE_URL.replace(/\/+$/, ''); // Remove trailing slash
  return `${base}/api/${API_VERSION}/${cleanPath}`;
};

export const endpoints = {
  // Authentication endpoints
  register: buildEndpoint('auth/register'),
  login: buildEndpoint('auth/login'),
  googleAuth: buildEndpoint('auth/google-auth'),
  saveProfile: buildEndpoint('auth/save-profile'),
  checkEmail: buildEndpoint('auth/check-email'),
  verifyEmail: buildEndpoint('auth/verify-email'),
  sendCode: buildEndpoint('auth/send-code'),
  changePassword: buildEndpoint('auth/change-password'),
  forgotPassword: buildEndpoint('auth/forgot-password'),
  
  // Workout endpoints
  getDashboard: buildEndpoint('workout/dashboard'),
  getAchievements: buildEndpoint('profile/achievements'),
  
  // Nutrition endpoints
  getNutrition: buildEndpoint('nutrition/data'),
  logMeal: buildEndpoint('nutrition/log'),
  scanMeal: buildEndpoint('nutrition/scan'),
  getAIFoodRecommendations: buildEndpoint('nutrition/ai-recommendations'),
  dismissInsight: buildEndpoint('nutrition/dismiss-insight'),
  
  // Profile endpoints
  getAIRecommendation: buildEndpoint('profile/ai-recommendation'),
  updateProfile: buildEndpoint('profile/update'),
  savePreferences: buildEndpoint('profile/preferences'),
  logWeight: buildEndpoint('profile/log-weight'),
  getNotifications: buildEndpoint('profile/notifications'),
  markNotificationRead: buildEndpoint('profile/notifications/read'),
  
  // Payment endpoints
  paystackInitialize: buildEndpoint('payment/initialize'),
  paystackVerify: buildEndpoint('payment/verify'),

  // System status / feature flags
  getSystemStatus: buildEndpoint('system/status'),
  
  // Legacy endpoints (temporary compatibility)
  legacy: {
    getDashboard: `${API_BASE_URL}/app/controllers/workout/get_dashboard_data.php`,
    getAchievements: `${API_BASE_URL}/app/controllers/profile/get_achievements.php`,
    getNutrition: `${API_BASE_URL}/app/controllers/nutrition/get_nutrition_data.php`,
    logMeal: `${API_BASE_URL}/app/controllers/nutrition/log_meal.php`,
    scanMeal: `${API_BASE_URL}/app/controllers/nutrition/scan_meal.php`,
    getAIFoodRecommendations: `${API_BASE_URL}/app/controllers/nutrition/ai_food_recommendations.php`,
    dismissInsight: `${API_BASE_URL}/app/controllers/nutrition/dismiss_insight.php`,
    getAIRecommendation: `${API_BASE_URL}/app/controllers/profile/get_ai_recommendation.php`,
    updateProfile: `${API_BASE_URL}/app/controllers/profile/update_profile.php`,
    savePreferences: `${API_BASE_URL}/app/controllers/profile/save_preferences.php`,
    logWeight: `${API_BASE_URL}/app/controllers/profile/log_weight.php`,
    getNotifications: `${API_BASE_URL}/app/controllers/profile/get_notifications.php`,
    markNotificationRead: `${API_BASE_URL}/app/controllers/profile/mark_notification_read.php`,
    paystackInitialize: `${API_BASE_URL}/app/controllers/payment/paystack_initialize.php`,
    paystackVerify: `${API_BASE_URL}/app/controllers/payment/paystack_verify.php`,
  }
};