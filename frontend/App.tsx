import React from 'react';
import { LogBox } from 'react-native';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { AppNavigator } from './src/navigation/AppNavigator';
import { CustomAlertModal } from './src/components/common/CustomAlertModal';
import { customAlertRef } from './src/components/common/CustomAlert';

LogBox.ignoreLogs([
  'SafeAreaView has been deprecated',
  'THREE.Clock: This module has been deprecated',
]);

export default function App() {
  return (
    <SafeAreaProvider>
      <AppNavigator />
      <CustomAlertModal ref={customAlertRef} />
    </SafeAreaProvider>
  );
}
