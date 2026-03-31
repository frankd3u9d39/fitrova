import React from 'react';
import { View, TouchableOpacity, StyleSheet, Animated } from 'react-native';
import { BottomTabBarProps } from '@react-navigation/bottom-tabs';
import { Ionicons } from '@expo/vector-icons';

export const DynamicTabBar = ({ state, descriptors, navigation }: BottomTabBarProps) => {
  const [animations] = React.useState(
    state.routes.map(() => new Animated.Value(0))
  );

  React.useEffect(() => {
    animations.forEach((anim, index) => {
      Animated.spring(anim, {
        toValue: state.index === index ? 1 : 0,
        useNativeDriver: true,
        tension: 80,
        friction: 10,
      }).start();
    });
  }, [state.index]);

  const getIconName = (routeName: string, focused: boolean): keyof typeof Ionicons.glyphMap => {
    const icons: Record<string, { focused: keyof typeof Ionicons.glyphMap; unfocused: keyof typeof Ionicons.glyphMap }> = {
      Home: { focused: 'home', unfocused: 'home-outline' },
      Workout: { focused: 'barbell', unfocused: 'barbell-outline' },
      Nutrition: { focused: 'restaurant', unfocused: 'restaurant-outline' },
      Profile: { focused: 'person', unfocused: 'person-outline' },
    };
    return focused ? icons[routeName]?.focused || 'home' : icons[routeName]?.unfocused || 'home-outline';
  };

  return (
    <View style={styles.container}>
      <View style={styles.tabBar}>
        {state.routes.map((route, index) => {
          const { options } = descriptors[route.key];
          const isFocused = state.index === index;

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

          const scale = animations[index].interpolate({
            inputRange: [0, 1],
            outputRange: [1, 1.2],
          });

          const translateY = animations[index].interpolate({
            inputRange: [0, 1],
            outputRange: [0, -8],
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
            >
              <Animated.View
                style={[
                  styles.iconContainer,
                  isFocused && styles.iconContainerActive,
                  {
                    transform: [{ scale }, { translateY }],
                  },
                ]}
              >
                <Ionicons
                  name={getIconName(route.name, isFocused)}
                  size={24}
                  color={isFocused ? '#FFFFFF' : '#6B7280'}
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
    bottom: 20,
    left: 20,
    right: 20,
    alignItems: 'center',
  },
  tabBar: {
    flexDirection: 'row',
    backgroundColor: '#1F2937',
    borderRadius: 30,
    paddingVertical: 12,
    paddingHorizontal: 20,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.3,
    shadowRadius: 20,
    elevation: 10,
    borderWidth: 1,
    borderColor: '#374151',
  },
  tabButton: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  iconContainer: {
    width: 50,
    height: 50,
    borderRadius: 25,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'transparent',
  },
  iconContainerActive: {
    backgroundColor: '#10B981',
    shadowColor: '#10B981',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.4,
    shadowRadius: 8,
    elevation: 6,
  },
});
