// Enhanced Theme System for Professional UI
// Updated to support all components across the app

export const theme = {
  // Color Palette
  colors: {
    // Primary
    primary: '#10B981',      // Emerald green
    primaryDark: '#059669',
    primaryLight: '#34D399',
    primaryAlpha: 'rgba(16, 185, 129, 0.1)',
    
    // Secondary & Accent
    secondary: '#3B82F6',    // Blue
    accent: '#8B5CF6',       // Purple
    
    // Neutrals
    white: '#FFFFFF',
    black: '#000000',
    gray: {
      50: '#F9FAFB',
      100: '#F3F4F6',
      200: '#E5E7EB',
      300: '#D1D5DB',
      400: '#9CA3AF',
      500: '#6B7280',
      600: '#4B5563',
      700: '#374151',
      800: '#1F2937',
      900: '#111827',
    },
    
    // Background
    background: '#FFFFFF',
    backgroundDark: '#F9FAFB',
    backgroundCard: '#FFFFFF',
    
    // Surface (cards, modals)
    surface: '#FFFFFF',
    surfaceDark: '#1F2937',
    surfaceLight: '#F3F4F6',
    
    // Text
    text: '#111827',
    textSecondary: '#6B7280',
    textTertiary: '#9CA3AF',
    textInverse: '#FFFFFF',
    
    // Borders
    border: '#E5E7EB',
    borderLight: '#F3F4F6',
    borderDark: '#D1D5DB',
    
    // Status
    success: '#10B981',
    successLight: '#D1FAE5',
    error: '#EF4444',
    errorLight: '#FEE2E2',
    warning: '#F59E0B',
    warningLight: '#FEF3C7',
    info: '#3B82F6',
    infoLight: '#DBEAFE',
    
    // Overlay
    overlay: 'rgba(0, 0, 0, 0.5)',
    overlayLight: 'rgba(0, 0, 0, 0.3)',
    
    // Gradients
    gradientStart: '#FFFFFF',
    gradientEnd: '#F3F4F6',
    gradientPrimary: ['#10B981', '#059669'],
    gradientDark: ['#1F2937', '#111827'],
    gradientLight: ['#FFFFFF', '#F9FAFB'],
  },
  
  // Typography
  typography: {
    // Scales
    fontSize: {
      xs: 10,
      sm: 12,
      base: 14,
      lg: 16,
      xl: 18,
      '2xl': 20,
      '3xl': 24,
      '4xl': 28,
    },
    fontWeight: {
      regular: '400' as const,
      medium: '500' as const,
      semibold: '600' as const,
      bold: '700' as const,
      heavy: '800' as const,
    },
    lineHeight: {
      tight: 1.2,
      normal: 1.5,
      relaxed: 1.625,
    },

    // Presets
    h1: {
      fontSize: 26,
      fontWeight: '800' as const,
      lineHeight: 32,
      letterSpacing: -0.5,
    },
    h2: {
      fontSize: 20,
      fontWeight: '700' as const,
      lineHeight: 26,
      letterSpacing: -0.3,
    },
    h3: {
      fontSize: 17,
      fontWeight: '600' as const,
      lineHeight: 22,
      letterSpacing: -0.2,
    },
    h4: {
      fontSize: 15,
      fontWeight: '600' as const,
      lineHeight: 20,
    },
    
    body: {
      fontSize: 14,
      fontWeight: '400' as const,
      lineHeight: 20,
    },
    bodyMedium: {
      fontSize: 14,
      fontWeight: '500' as const,
      lineHeight: 20,
    },
    bodySmall: {
      fontSize: 12,
      fontWeight: '400' as const,
      lineHeight: 16,
    },
    
    caption: {
      fontSize: 11,
      fontWeight: '400' as const,
      lineHeight: 14,
    },
    captionBold: {
      fontSize: 11,
      fontWeight: '600' as const,
      lineHeight: 14,
      letterSpacing: 0.5,
    },
    
    label: {
      fontSize: 9,
      fontWeight: '700' as const,
      lineHeight: 10,
      letterSpacing: 1,
      textTransform: 'uppercase' as const,
    },
    button: {
      fontSize: 16,
      fontWeight: '600' as const,
      lineHeight: 24,
      letterSpacing: 0.5,
    },
  },
  
  // Spacing (4px base unit)
  spacing: {
    none: 0,
    xxs: 4,
    xs: 8,
    sm: 12,
    md: 16,
    lg: 20,
    xl: 24,
    xxl: 32,
    xxxl: 40,
    '2xl': 32, // for backward compatibility with some screens
    '3xl': 48,
  },
  
  // Border Radius
  borderRadius: {
    xs: 4,
    sm: 8,
    md: 12,
    lg: 16,
    xl: 20,
    xxl: 24,
    full: 9999,
  },
  
  // Shadows
  shadows: {
    none: {
      shadowColor: 'transparent',
      shadowOffset: { width: 0, height: 0 },
      shadowOpacity: 0,
      shadowRadius: 0,
      elevation: 0,
    },
    sm: {
      shadowColor: '#1F2937', // Deep slate tint for soft light shadow
      shadowOffset: { width: 0, height: 2 },
      shadowOpacity: 0.04,
      shadowRadius: 6,
      elevation: 1,
    },
    md: {
      shadowColor: '#1F2937', // Deep slate tint for ambient card shadow
      shadowOffset: { width: 0, height: 4 },
      shadowOpacity: 0.06,
      shadowRadius: 12,
      elevation: 2,
    },
    lg: {
      shadowColor: '#10B981', // Emerald brand-tinted glow for featured elements
      shadowOffset: { width: 0, height: 8 },
      shadowOpacity: 0.06,
      shadowRadius: 20,
      elevation: 3,
    },
    xl: {
      shadowColor: '#1F2937',
      shadowOffset: { width: 0, height: 16 },
      shadowOpacity: 0.08,
      shadowRadius: 24,
      elevation: 6,
    },
  },
  
  // Animation Durations
  animation: {
    fast: 150,
    normal: 250,
    slow: 350,
  },
  
  // Z-Index
  zIndex: {
    base: 0,
    dropdown: 1000,
    sticky: 1100,
    fixed: 1200,
    modalBackdrop: 1300,
    modal: 1400,
    popover: 1500,
    tooltip: 1600,
  },
};

export const colors = theme.colors;
export const typography = theme.typography;
export const spacing = theme.spacing;

export type Theme = typeof theme;
