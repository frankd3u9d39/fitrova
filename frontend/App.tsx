import React from 'react';
import { LogBox } from 'react-native';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { AppNavigator } from './src/navigation/AppNavigator';
import { CustomAlertModal } from './src/components/common/CustomAlertModal';
import { customAlertRef } from './src/components/common/CustomAlert';

LogBox.ignoreLogs([
  'SafeAreaView has been deprecated',
  'THREE.Clock: This module has been deprecated',
  'EXGL: gl.pixelStorei() doesn\'t support this parameter yet!',
]);

// Global fetch logger to identify failing network requests
const originalFetch = global.fetch;
global.fetch = async (...args) => {
  const url = typeof args[0] === 'string' ? args[0] : (args[0] as any).url;
  console.log(`🌐 [FETCH START]: ${url}`);
  try {
    const response = await originalFetch(...args);
    console.log(`✅ [FETCH SUCCESS]: ${url} (${response.status})`);
    return response;
  } catch (error) {
    console.error(`❌ [FETCH ERROR]: ${url}`, error);
    throw error;
  }
};

export default function App() {
  return (
    <SafeAreaProvider>
      <AppNavigator />
      <CustomAlertModal ref={customAlertRef} />
    </SafeAreaProvider>
  );
}
