// iOS-native design tokens — Apple Human Interface Guidelines values
// (system colors, SF-scale typography, 8pt spacing, materials).
// This is additive to theme/index.ts (which many existing screens still
// import) so nothing else breaks while screens migrate over one at a time.

export type ColorScheme = 'light' | 'dark';

// Fitrova's brand tint — the one accent color used across the whole app,
// the way a real iOS app has a single "tint color".
const brand = {
  tint: '#10B981',
  tintDark: '#059669',
  tintLight: '#34D399',
};

// Apple system colors (dynamic — differ between light and dark).
const systemColors = {
  light: {
    red: '#FF3B30',
    orange: '#FF9500',
    yellow: '#FFCC00',
    green: '#34C759',
    blue: '#007AFF',
    purple: '#AF52DE',
  },
  dark: {
    red: '#FF453A',
    orange: '#FF9F0A',
    yellow: '#FFD60A',
    green: '#30D158',
    blue: '#0A84FF',
    purple: '#BF5AF2',
  },
};

// Apple's gray ramp (systemGray ... systemGray6), light and dark variants.
const grays = {
  light: {
    gray: '#8E8E93',
    gray2: '#AEAEB2',
    gray3: '#C7C7CC',
    gray4: '#D1D1D6',
    gray5: '#E5E5EA',
    gray6: '#F2F2F7',
  },
  dark: {
    gray: '#8E8E93',
    gray2: '#636366',
    gray3: '#48484A',
    gray4: '#3A3A3C',
    gray5: '#2C2C2E',
    gray6: '#1C1C1E',
  },
};

function buildPalette(scheme: ColorScheme) {
  const sys = systemColors[scheme];
  const gray = grays[scheme];
  const isDark = scheme === 'dark';

  return {
    scheme,
    tint: brand.tint,
    tintDark: brand.tintDark,
    tintLight: brand.tintLight,

    // Backgrounds (the "grouped" family is iOS's inset-list/settings look —
    // secondary surface behind primary content cards)
    background: isDark ? '#000000' : '#FFFFFF',
    secondaryBackground: isDark ? gray.gray6 : gray.gray6,
    tertiaryBackground: isDark ? gray.gray5 : '#FFFFFF',
    groupedBackground: isDark ? '#000000' : gray.gray6,
    secondaryGroupedBackground: isDark ? gray.gray6 : '#FFFFFF',

    // Labels
    label: isDark ? '#FFFFFF' : '#000000',
    secondaryLabel: isDark ? 'rgba(235,235,245,0.6)' : 'rgba(60,60,67,0.6)',
    tertiaryLabel: isDark ? 'rgba(235,235,245,0.3)' : 'rgba(60,60,67,0.3)',
    quaternaryLabel: isDark ? 'rgba(235,235,245,0.18)' : 'rgba(60,60,67,0.18)',
    inverseLabel: isDark ? '#000000' : '#FFFFFF',

    // Separators
    separator: isDark ? 'rgba(84,84,88,0.6)' : 'rgba(60,60,67,0.29)',
    opaqueSeparator: isDark ? gray.gray4 : '#C6C6C8',

    // Fills — translucent overlays for control backgrounds (switches, chips)
    fill: isDark ? 'rgba(120,120,128,0.36)' : 'rgba(120,120,128,0.2)',
    secondaryFill: isDark ? 'rgba(120,120,128,0.32)' : 'rgba(120,120,128,0.16)',
    tertiaryFill: isDark ? 'rgba(118,118,128,0.24)' : 'rgba(118,118,128,0.12)',

    // Semantic status — deliberately distinct from `tint`, unlike the
    // legacy theme where success/info literally equal the brand colors
    success: sys.green,
    warning: sys.orange,
    error: sys.red,
    info: sys.blue,

    gray: gray.gray,
    gray2: gray.gray2,
    gray3: gray.gray3,
    gray4: gray.gray4,
    gray5: gray.gray5,
    gray6: gray.gray6,

    card: isDark ? gray.gray6 : '#FFFFFF',
    overlay: isDark ? 'rgba(0,0,0,0.6)' : 'rgba(0,0,0,0.4)',
  };
}

export const iosLight = buildPalette('light');
export const iosDark = buildPalette('dark');

// SF-scale type ramp — real Apple HIG point sizes / line heights / weights.
// fontFamily 'System' resolves to San Francisco on iOS and Roboto on
// Android automatically — the idiomatic RN way to get native type.
export const iosTypography = {
  largeTitle: { fontFamily: 'System', fontSize: 34, lineHeight: 41, fontWeight: '700' as const, letterSpacing: 0.37 },
  title1: { fontFamily: 'System', fontSize: 28, lineHeight: 34, fontWeight: '700' as const, letterSpacing: 0.36 },
  title2: { fontFamily: 'System', fontSize: 22, lineHeight: 28, fontWeight: '700' as const, letterSpacing: 0.35 },
  title3: { fontFamily: 'System', fontSize: 20, lineHeight: 25, fontWeight: '600' as const, letterSpacing: 0.38 },
  headline: { fontFamily: 'System', fontSize: 17, lineHeight: 22, fontWeight: '600' as const, letterSpacing: -0.43 },
  body: { fontFamily: 'System', fontSize: 17, lineHeight: 22, fontWeight: '400' as const, letterSpacing: -0.43 },
  callout: { fontFamily: 'System', fontSize: 16, lineHeight: 21, fontWeight: '400' as const, letterSpacing: -0.31 },
  subheadline: { fontFamily: 'System', fontSize: 15, lineHeight: 20, fontWeight: '400' as const, letterSpacing: -0.24 },
  footnote: { fontFamily: 'System', fontSize: 13, lineHeight: 18, fontWeight: '400' as const, letterSpacing: -0.08 },
  caption1: { fontFamily: 'System', fontSize: 12, lineHeight: 16, fontWeight: '400' as const, letterSpacing: 0 },
  caption2: { fontFamily: 'System', fontSize: 11, lineHeight: 13, fontWeight: '400' as const, letterSpacing: 0.07 },
};

// 8pt grid — iOS's standard spacing unit, 16/20 as the usual screen margin.
export const iosSpacing = {
  xxs: 2,
  xs: 4,
  sm: 8,
  md: 12,
  lg: 16,
  xl: 20,
  xxl: 24,
  xxxl: 32,
};

// iOS corner radii — cards, sheets, buttons. RN can't do Apple's true
// "continuous" superellipse curve without a native module, so these are
// the closest circular-radius approximations at each usual size.
export const iosRadius = {
  sm: 8,
  md: 10,
  lg: 14,
  xl: 18,
  xxl: 22,
  pill: 999,
};

// expo-blur `intensity` presets mapped to Apple's named materials.
export const iosMaterials = {
  ultraThin: 20,
  thin: 40,
  regular: 65,
  thick: 90,
  chrome: 100,
};

export function iosShadow(scheme: ColorScheme) {
  const isDark = scheme === 'dark';
  return {
    card: {
      shadowColor: '#000000',
      shadowOffset: { width: 0, height: isDark ? 0 : 2 },
      shadowOpacity: isDark ? 0 : 0.06,
      shadowRadius: 12,
      elevation: isDark ? 0 : 2,
    },
    floating: {
      shadowColor: '#000000',
      shadowOffset: { width: 0, height: 8 },
      shadowOpacity: isDark ? 0.5 : 0.12,
      shadowRadius: 24,
      elevation: 6,
    },
  };
}
