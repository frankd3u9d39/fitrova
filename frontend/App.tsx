import React, { useEffect } from 'react';
import { LogBox, AppState, AppStateStatus } from 'react-native';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { AppNavigator } from './src/navigation/AppNavigator';
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
  
  if (config.loggingEnabled) {
    console.log(`🌐 [FETCH START]: ${url}`);
  }

  const maxRetries = 3;
  let lastError: Error | null = null;
  
  for (let attempt = 1; attempt <= maxRetries; attempt++) {
    try {
      const startTime = Date.now();
      const response = await originalFetch(...args);
      const endTime = Date.now();
      
      if (config.loggingEnabled) {
        console.log(`✅ [FETCH SUCCESS]: ${url} (${response.status}) - ${endTime - startTime}ms`);
      }
      
      // Always return the response — let each screen decide how to handle
      // non-2xx status codes (401, 403, 409, etc.) rather than throwing here.
      return response;
    } catch (error) {
      // Only reaches here on a genuine network failure (no response at all)
      lastError = error as Error;
      
      if (config.loggingEnabled) {
        console.warn(`⚠️ [FETCH RETRY ${attempt}/${maxRetries}]:`);
        console.warn(`   URL: ${url}`);
        console.warn(`   Error:`, error);
      }
      
      // Exponential backoff before next attempt
      if (attempt < maxRetries) {
        await new Promise(resolve => setTimeout(resolve, Math.pow(2, attempt) * 1000));
      }
    }
  }
  
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
