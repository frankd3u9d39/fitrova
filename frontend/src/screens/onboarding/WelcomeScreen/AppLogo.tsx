import React from 'react';
import { View, StyleSheet } from 'react-native';
import Svg, { Path, Circle, G, Defs, LinearGradient, Stop } from 'react-native-svg';
import { colors } from '../../../theme';

const AppLogo: React.FC = () => {
  return (
    <View style={styles.container}>
      <Svg width="80" height="80" viewBox="0 0 80 80" fill="none">
        <Defs>
          <LinearGradient id="logoGradient" x1="0%" y1="0%" x2="100%" y2="100%">
            <Stop offset="0%" stopColor={colors.primary} />
            <Stop offset="100%" stopColor={colors.secondary} />
          </LinearGradient>
        </Defs>
        
        {/* Outer circle */}
        <Circle
          cx="40"
          cy="40"
          r="36"
          fill="url(#logoGradient)"
          opacity="0.1"
        />
        
        {/* Main icon - Fitness person with AI elements */}
        <G>
          {/* Person running silhouette */}
          <Path
            d="M35 20 C35 17 37 15 40 15 C43 15 45 17 45 20 C45 23 43 25 40 25 C37 25 35 23 35 20 Z"
            fill="url(#logoGradient)"
          />
          
          {/* Body and legs in running pose */}
          <Path
            d="M40 28 L38 35 L35 45 L32 52 M40 28 L42 35 L48 42 M38 35 L45 38 L52 36"
            stroke="url(#logoGradient)"
            strokeWidth="3"
            strokeLinecap="round"
            strokeLinejoin="round"
          />
          
          {/* AI circuit nodes */}
          <Circle cx="25" cy="30" r="2.5" fill={colors.accent} />
          <Circle cx="55" cy="35" r="2.5" fill={colors.accent} />
          <Circle cx="30" cy="55" r="2.5" fill={colors.accent} />
          
          {/* Connecting lines for AI effect */}
          <Path
            d="M25 30 L35 25 M55 35 L48 38 M30 55 L35 45"
            stroke={colors.accent}
            strokeWidth="1"
            strokeDasharray="2,2"
            opacity="0.5"
          />
          
          {/* Heartbeat line */}
          <Path
            d="M15 60 L20 60 L23 55 L26 65 L29 60 L35 60"
            stroke={colors.secondary}
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
          />
        </G>
      </Svg>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    alignItems: 'center',
    justifyContent: 'center',
  },
});

export default AppLogo;
