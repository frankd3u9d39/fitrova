import React, { createContext, useContext, useEffect, useMemo, useState } from 'react';
import { Appearance } from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import {
  ColorScheme,
  iosLight,
  iosDark,
  iosTypography,
  iosSpacing,
  iosRadius,
  iosMaterials,
  iosShadow,
} from './ios';

type ThemePreference = 'light' | 'dark' | 'system';

interface ThemeContextValue {
  colors: typeof iosLight;
  typography: typeof iosTypography;
  spacing: typeof iosSpacing;
  radius: typeof iosRadius;
  materials: typeof iosMaterials;
  shadow: ReturnType<typeof iosShadow>;
  scheme: ColorScheme;
  preference: ThemePreference;
  setPreference: (pref: ThemePreference) => void;
}

const STORAGE_KEY = 'fitrova_theme_preference';

const ThemeContext = createContext<ThemeContextValue | null>(null);

export const ThemeProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [preference, setPreferenceState] = useState<ThemePreference>('system');
  const [systemScheme, setSystemScheme] = useState<ColorScheme>(
    (Appearance.getColorScheme() as ColorScheme) || 'light'
  );

  useEffect(() => {
    AsyncStorage.getItem(STORAGE_KEY).then((saved) => {
      if (saved === 'light' || saved === 'dark' || saved === 'system') {
        setPreferenceState(saved);
      }
    });

    const sub = Appearance.addChangeListener(({ colorScheme }) => {
      setSystemScheme((colorScheme as ColorScheme) || 'light');
    });
    return () => sub.remove();
  }, []);

  const setPreference = (pref: ThemePreference) => {
    setPreferenceState(pref);
    AsyncStorage.setItem(STORAGE_KEY, pref).catch(() => {});
  };

  const scheme: ColorScheme = preference === 'system' ? systemScheme : preference;

  const value = useMemo<ThemeContextValue>(() => {
    const colors = scheme === 'dark' ? iosDark : iosLight;
    return {
      colors,
      typography: iosTypography,
      spacing: iosSpacing,
      radius: iosRadius,
      materials: iosMaterials,
      shadow: iosShadow(scheme),
      scheme,
      preference,
      setPreference,
    };
  }, [scheme, preference]);

  return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>;
};

export function useTheme(): ThemeContextValue {
  const ctx = useContext(ThemeContext);
  if (!ctx) {
    throw new Error('useTheme() must be used within a <ThemeProvider>');
  }
  return ctx;
}
