import React, { useEffect } from 'react';
import { LogBox, AppState, AppStateStatus } from 'react-native';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import * as SplashScreen from 'expo-splash-screen';
import { AppNavigator } from './src/navigation/AppNavigator';

// Keep the native splash screen visible while loading resources
SplashScreen.preventAutoHideAsync().catch(() => {
  /* Prevent crash if called in an unsupported environment */
});
import { CustomAlertModal } from './src/components/common/CustomAlertModal';
import { customAlertRef } from './src/components/common/CustomAlert';
import { config, validateEnvironment } from './src/config';
import { localNotificationService } from './src/services/notifications/localNotificationService';

LogBox.ignoreLogs([
  'SafeAreaView has been deprecated',
  'THREE.Clock: This module has been deprecated',
  'EXGL: gl.pixelStorei() doesn\'t support this parameter yet!',
]);

// Enhanced global fetch interceptor with retry logic (network errors only)
const originalFetch = global.fetch;
global.fetch = async (...args: Parameters<typeof fetch>) => {
  const url = typeof args[0] === 'string' ? args[0] : (args[0] as any).url;
  const options = args[1] as RequestInit | undefined;

  if (config.loggingEnabled) {
    console.log(`🌐 [FETCH START]: ${url}`);
  }

  // Detect file/multipart uploads or AI analysis requests — these need a long timeout and NO retries
  const isLongRunning =
    options?.body instanceof FormData ||
    (url as string).includes('ai_form_analyzer') ||
    ((url as string).includes('youtube_workout_controller') && (url as string).includes('action=analyze'));

  // 120 s for uploads and heavy AI analysis (Gemini/HF can be slow), 30 s for everything else
  const TIMEOUT_MS = isLongRunning ? 120_000 : 30_000;
  // maxRetries = 0 → single attempt, no retry; maxRetries = N → up to N+1 attempts
  const maxRetries = isLongRunning ? 0 : 3;
  let lastError: Error | null = null;
  let attempt = 0;

  do {
    attempt++;
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), TIMEOUT_MS);

    // Merge the caller's signal (if any) with ours
    const fetchArgs: Parameters<typeof fetch> = [
      args[0],
      { ...options, signal: controller.signal },
    ];

    try {
      const startTime = Date.now();
      const response = await originalFetch(...fetchArgs);
      clearTimeout(timer);
      const endTime = Date.now();

      if (config.loggingEnabled) {
        console.log(`✅ [FETCH SUCCESS]: ${url} (${response.status}) - ${endTime - startTime}ms`);
      }

      return response;
    } catch (error: any) {
      clearTimeout(timer);
      lastError = error as Error;

      const isAbort = error?.name === 'AbortError';

      if (config.loggingEnabled) {
        if (isAbort) {
          console.warn(`⏱️ [FETCH TIMEOUT]: ${url} (>${TIMEOUT_MS / 1000}s — request aborted)`);
        } else if (attempt <= maxRetries) {
          console.warn(`⚠️ [FETCH RETRY ${attempt}/${maxRetries}]:`);
          console.warn(`   URL: ${url}`);
          console.warn(`   Error:`, error);
        }
      }

      // Don't retry on timeout or if we've exhausted retries
      if (isAbort || attempt > maxRetries) break;

      // Exponential backoff before next attempt
      await new Promise(resolve => setTimeout(resolve, Math.pow(2, attempt) * 1000));
    }
  } while (attempt <= maxRetries);

  if (config.loggingEnabled) {
    console.error(`❌ [FETCH FINAL ERROR]:`);
    console.error(`   URL: ${url}`);
    console.error(`   Error:`, lastError);
  }
  throw lastError;
};


// Global error handler
const setupGlobalErrorHandling = () => {
  const originalErrorHandler = ErrorUtils.getGlobalHandler();
  
  ErrorUtils.setGlobalHandler((error, isFatal) => {
    console.error('🚨 [GLOBAL ERROR]:', error, 'Fatal:', isFatal);
    
    // Report to error tracking service (Sentry, etc.)
    // In production: send to error tracking service
    
    // Call original handler
    if (originalErrorHandler) {
      originalErrorHandler(error, isFatal);
    }
  });
};

export default function App() {
  useEffect(() => {
    // Initialize global error handling
    setupGlobalErrorHandling();
    
    // Validate environment configuration
    const warnings = validateEnvironment();
    if (warnings.length > 0) {
      console.warn('⚠️ Environment Configuration Warnings:');
      warnings.forEach(warning => console.warn(`  • ${warning}`));
    }
    
    // Log environment info (development only)
    if (config.loggingEnabled) {
      console.log('🚀 Fitrova App Starting...');
      console.log(`📱 Environment: ${config.env}`);
      console.log(`🌐 API Base URL: ${config.apiBaseUrl}`);
      console.log(`🤖 AI Service URL: ${config.aiServiceUrl}`);
    }



    // AppState change listener to manage local reminders lifecycle
    const handleAppStateChange = (nextAppState: AppStateStatus) => {
      if (nextAppState === 'background') {
        // Schedule reminders when app is exited/backgrounded
        localNotificationService.scheduleDailyReminder();
        localNotificationService.scheduleInactivityReminder();
      } else if (nextAppState === 'active') {
        // Cancel all notifications when app is active/foregrounded
        localNotificationService.cancelAllNotifications();
      }
    };

    const subscription = AppState.addEventListener('change', handleAppStateChange);

    return () => {
      subscription.remove();
    };
  }, []);

  return (
    <SafeAreaProvider>
      <AppNavigator />
      <CustomAlertModal ref={customAlertRef} />
    </SafeAreaProvider>
  );
}
