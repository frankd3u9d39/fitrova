import React, { useEffect, useRef } from 'react';
import { View, TouchableOpacity, StyleSheet, Dimensions, Animated } from 'react-native';
import { BottomTabBarProps } from '@react-navigation/bottom-tabs';
import { Ionicons } from '@expo/vector-icons';
import Svg, { Path } from 'react-native-svg';

const AnimatedPath = Animated.createAnimatedComponent(Path);

const { width: SCREEN_WIDTH } = Dimensions.get('window');
const TAB_BAR_WIDTH = SCREEN_WIDTH - 40;
const TAB_BAR_HEIGHT = 65;
const CORNER_RADIUS = 20;
const PADDING_H = 20;

export const DynamicTabBar = ({ state, descriptors, navigation }: BottomTabBarProps) => {
  const tabWidth = (TAB_BAR_WIDTH - PADDING_H * 2) / state.routes.length;
  
  // Use React Native's built-in Animated to completely avoid react-native-reanimated native crashes
  const slideAnim = useRef(new Animated.Value(state.index)).current;
  const pathRef = useRef<any>(null);

  // Icon animations array
  const iconAnims = useRef(state.routes.map((_, i) => new Animated.Value(i === state.index ? 1 : 0))).current;

  const generatePath = (centerX: number) => {
    const curveDepth = 40;
    const curveWidth = 90; 

    const startCurveX = centerX - curveWidth / 2;
    const endCurveX = centerX + curveWidth / 2;
    
    const safeStartCurveX = Math.max(CORNER_RADIUS, startCurveX);
    const safeEndCurveX = Math.min(TAB_BAR_WIDTH - CORNER_RADIUS, endCurveX);

    return `
      M 0,${CORNER_RADIUS}
      A ${CORNER_RADIUS},${CORNER_RADIUS} 0 0,1 ${CORNER_RADIUS},0
      L ${safeStartCurveX},0
      C ${centerX - curveWidth / 4},0 ${centerX - curveWidth / 3},${curveDepth} ${centerX},${curveDepth}
      C ${centerX + curveWidth / 3},${curveDepth} ${centerX + curveWidth / 4},0 ${safeEndCurveX},0
      L ${TAB_BAR_WIDTH - CORNER_RADIUS},0
      A ${CORNER_RADIUS},${CORNER_RADIUS} 0 0,1 ${TAB_BAR_WIDTH},${CORNER_RADIUS}
      L ${TAB_BAR_WIDTH},${TAB_BAR_HEIGHT - CORNER_RADIUS}
      A ${CORNER_RADIUS},${CORNER_RADIUS} 0 0,1 ${TAB_BAR_WIDTH - CORNER_RADIUS},${TAB_BAR_HEIGHT}
      L ${CORNER_RADIUS},${TAB_BAR_HEIGHT}
      A ${CORNER_RADIUS},${CORNER_RADIUS} 0 0,1 0,${TAB_BAR_HEIGHT - CORNER_RADIUS}
      Z
    `;
  };

  useEffect(() => {
    // Animate the main sliding curve
    Animated.spring(slideAnim, {
      toValue: state.index,
      friction: 8,
      tension: 60,
      useNativeDriver: false, // Must be false to listen to value changes accurately for setNativeProps
    }).start();

    // Animate the individual icons
    iconAnims.forEach((anim, i) => {
      Animated.spring(anim, {
        toValue: i === state.index ? 1 : 0,
        friction: 8,
        tension: 60,
        useNativeDriver: true,
      }).start();
    });
  }, [state.index]);

  useEffect(() => {
    // Listen to the animation and update the SVG path using setNativeProps
    // This provides 60fps path morphing without needing react-native-reanimated!
    const listener = slideAnim.addListener(({ value }) => {
      const centerX = PADDING_H + (value + 0.5) * tabWidth;
      const d = generatePath(centerX);
      if (pathRef.current) {
        pathRef.current.setNativeProps({ d });
      }
    });

    // Set initial path
    const initialCenterX = PADDING_H + (state.index + 0.5) * tabWidth;
    if (pathRef.current) {
      pathRef.current.setNativeProps({ d: generatePath(initialCenterX) });
    }

    return () => {
      slideAnim.removeListener(listener);
    };
  }, []);

  const getIconName = (routeName: string, focused: boolean): keyof typeof Ionicons.glyphMap => {
    const icons: Record<string, { focused: keyof typeof Ionicons.glyphMap; unfocused: keyof typeof Ionicons.glyphMap }> = {
      Home: { focused: 'home', unfocused: 'home-outline' },
      Workout: { focused: 'barbell', unfocused: 'barbell-outline' },
      Nutrition: { focused: 'restaurant', unfocused: 'restaurant-outline' },
      Profile: { focused: 'person', unfocused: 'person-outline' },
    };
    return focused ? icons[routeName]?.focused || 'home' : icons[routeName]?.unfocused || 'home-outline';
  };

  const indicatorTranslateX = slideAnim.interpolate({
    inputRange: state.routes.map((_, i) => i),
    outputRange: state.routes.map(i => PADDING_H + (i + 0.5) * tabWidth - 24), // 24 is half indicator width
  });

  return (
    <View style={styles.container}>
      <Svg width={TAB_BAR_WIDTH} height={TAB_BAR_HEIGHT} style={styles.svgBackground}>
        <AnimatedPath ref={pathRef} fill="#1F2937" />
      </Svg>

      <Animated.View 
        style={[
          styles.activeIndicator, 
          { transform: [{ translateX: indicatorTranslateX }, { translateY: -24 }] }
        ]} 
      />

      <View style={styles.tabsContainer}>
        {state.routes.map((route, index) => {
          const { options } = descriptors[route.key];
          const isFocused = state.index === index;
          const animValue = iconAnims[index];

          const onPress = () => {
            const event = navigation.emit({
              type: 'tabPress',
              target: route.key,
              canPreventDefault: true,
            });

            if (!isFocused && !event.defaultPrevented) {
              navigation.navigate(route.name);
            }
          };

          const iconTranslateY = animValue.interpolate({
            inputRange: [0, 1],
            outputRange: [0, -24]
          });

          return (
            <TouchableOpacity
              key={route.key}
              accessibilityRole="button"
              accessibilityState={isFocused ? { selected: true } : {}}
              accessibilityLabel={options.tabBarAccessibilityLabel}
              testID={options.tabBarTestID}
              onPress={onPress}
              style={styles.tabButton}
              activeOpacity={1}
            >
              <Animated.View style={[styles.iconContainer, { transform: [{ translateY: iconTranslateY }] }]}>
                <Ionicons
                  name={getIconName(route.name, isFocused)}
                  size={24}
                  color={isFocused ? '#10B981' : '#9CA3AF'}
                />
              </Animated.View>
            </TouchableOpacity>
          );
        })}
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    position: 'absolute',
    bottom: 24,
    left: 20,
    right: 20,
    height: TAB_BAR_HEIGHT,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.25,
    shadowRadius: 20,
    elevation: 10,
  },
  svgBackground: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
  },
  tabsContainer: {
    flex: 1,
    flexDirection: 'row',
    paddingHorizontal: PADDING_H,
  },
  tabButton: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  iconContainer: {
    width: 48,
    height: 48,
    borderRadius: 24,
    alignItems: 'center',
    justifyContent: 'center',
    zIndex: 2,
  },
  activeIndicator: {
    position: 'absolute',
    top: (TAB_BAR_HEIGHT - 48) / 2, 
    left: 0,
    width: 48,
    height: 48,
    borderRadius: 24,
    backgroundColor: '#10B981', 
    zIndex: 1,
    shadowColor: '#10B981',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.4,
    shadowRadius: 8,
    elevation: 6,
  },
});
