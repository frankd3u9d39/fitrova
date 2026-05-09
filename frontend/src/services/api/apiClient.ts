export const API_BASE_URL = 'http://10.127.100.154/Fitrova/backend';

export const endpoints = {
  register: `${API_BASE_URL}/app/controllers/auth/register.php`,
  login: `${API_BASE_URL}/app/controllers/auth/login.php`,
  saveProfile: `${API_BASE_URL}/app/controllers/auth/save_profile.php`,
  checkEmail: `${API_BASE_URL}/app/controllers/auth/check_email.php`,
  getDashboard: `${API_BASE_URL}/app/controllers/workout/get_dashboard_data.php`,
  getAchievements: `${API_BASE_URL}/app/controllers/profile/get_achievements.php`,
  getNutrition: `${API_BASE_URL}/app/controllers/nutrition/get_nutrition_data.php`,
  logMeal: `${API_BASE_URL}/app/controllers/nutrition/log_meal.php`,
  scanMeal: `${API_BASE_URL}/app/controllers/nutrition/scan_meal.php`,
  verifyEmail: `${API_BASE_URL}/app/controllers/auth/verify_email.php`,
  sendCode: `${API_BASE_URL}/app/controllers/auth/send_code.php`,
  getAIRecommendation: `${API_BASE_URL}/app/controllers/profile/get_ai_recommendation.php`,
};
