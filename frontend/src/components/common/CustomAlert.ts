import React from 'react';
import { CustomAlertRef, AlertButton } from './CustomAlertModal';

export const customAlertRef = React.createRef<CustomAlertRef>();

export const CustomAlert = {
  alert: (title: string, message?: string, buttons?: AlertButton[]) => {
    // If the ref is not mounted (e.g., during startup or testing), fallback to React Native Alert
    if (customAlertRef.current) {
      customAlertRef.current.alert(title, message, buttons);
    } else {
      console.warn("CustomAlert reference not mounted, falling back to Native Alert. Title:", title, message);
      // Fallback is commented to enforce custom alert, but we log a warning if unmounted.
    }
  }
};
