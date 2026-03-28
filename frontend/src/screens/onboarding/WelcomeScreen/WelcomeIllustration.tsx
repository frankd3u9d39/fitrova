import React from 'react';
import { View, StyleSheet, Dimensions } from 'react-native';
import Svg, { Path, Circle, Ellipse, G, Defs, LinearGradient, Stop, Rect } from 'react-native-svg';
import { colors, spacing } from '../../../theme';

const { width } = Dimensions.get('window');
const illustrationWidth = width * 0.8;
const illustrationHeight = illustrationWidth * 0.9;

const WelcomeIllustration: React.FC = () => {
  return (
    <View style={styles.container}>
      <Svg
        width={illustrationWidth}
        height={illustrationHeight}
        viewBox="0 0 300 270"
        fill="none"
      >
        <Defs>
          <LinearGradient id="personGradient" x1="0%" y1="0%" x2="0%" y2="100%">
            <Stop offset="0%" stopColor={colors.primary} />
            <Stop offset="100%" stopColor={colors.primaryDark} />
          </LinearGradient>
          <LinearGradient id="accentGradient" x1="0%" y1="0%" x2="100%" y2="0%">
            <Stop offset="0%" stopColor={colors.secondary} />
            <Stop offset="100%" stopColor={colors.accent} />
          </LinearGradient>
        </Defs>

        {/* Background elements - Data visualization */}
        <G opacity="0.15">
          {/* Grid lines */}
          <Path d="M20 50 L280 50" stroke={colors.gray[300]} strokeWidth="1" />
          <Path d="M20 100 L280 100" stroke={colors.gray[300]} strokeWidth="1" />
          <Path d="M20 150 L280 150" stroke={colors.gray[300]} strokeWidth="1" />
          <Path d="M20 200 L280 200" stroke={colors.gray[300]} strokeWidth="1" />
        </G>

        {/* Activity graph in background */}
        <G opacity="0.2">
          <Path
            d="M30 180 L60 160 L90 140 L120 155 L150 120 L180 135 L210 100 L240 115 L270 90"
            stroke={colors.secondary}
            strokeWidth="3"
            strokeLinecap="round"
            fill="none"
          />
        </G>

        {/* Main person running illustration */}
        <G transform="translate(100, 80)">
          {/* Head */}
          <Circle cx="50" cy="20" r="18" fill="url(#personGradient)" />
          
          {/* Body */}
          <Ellipse cx="50" cy="60" rx="20" ry="30" fill="url(#personGradient)" />
          
          {/* Arms - running pose */}
          <Path
            d="M35 45 Q25 50 20 65"
            stroke="url(#personGradient)"
            strokeWidth="8"
            strokeLinecap="round"
            fill="none"
          />
          <Path
            d="M65 45 Q75 55 85 45"
            stroke="url(#personGradient)"
            strokeWidth="8"
            strokeLinecap="round"
            fill="none"
          />
          
          {/* Legs - running pose */}
          <Path
            d="M45 85 Q40 110 35 135"
            stroke="url(#personGradient)"
            strokeWidth="8"
            strokeLinecap="round"
            fill="none"
          />
          <Path
            d="M55 85 Q65 105 75 120"
            stroke="url(#personGradient)"
            strokeWidth="8"
            strokeLinecap="round"
            fill="none"
          />
        </G>

        {/* AI and Tech elements */}
        {/* Heartbeat monitor */}
        <G transform="translate(20, 30)">
          <Rect
            x="0"
            y="0"
            width="70"
            height="40"
            rx="8"
            fill={colors.white}
            opacity="0.9"
          />
          <Path
            d="M10 20 L20 20 L25 10 L30 30 L35 20 L45 20"
            stroke={colors.secondary}
            strokeWidth="2.5"
            strokeLinecap="round"
            fill="none"
          />
          <Circle cx="55" cy="20" r="3" fill={colors.secondary} />
        </G>

        {/* Steps counter */}
        <G transform="translate(210, 160)">
          <Rect
            x="0"
            y="0"
            width="70"
            height="40"
            rx="8"
            fill={colors.white}
            opacity="0.9"
          />
          <Path
            d="M15 15 L20 25 L25 15 L30 25"
            stroke={colors.primary}
            strokeWidth="2.5"
            strokeLinecap="round"
            fill="none"
          />
          <Circle cx="50" cy="20" r="8" fill="none" stroke={colors.primary} strokeWidth="2" />
          <Path
            d="M50 15 L50 20 L53 23"
            stroke={colors.primary}
            strokeWidth="2"
            strokeLinecap="round"
            fill="none"
          />
        </G>

        {/* AI nodes and connections */}
        <G opacity="0.6">
          {/* Node 1 */}
          <Circle cx="50" cy="120" r="6" fill={colors.accent} />
          <Circle cx="50" cy="120" r="10" fill="none" stroke={colors.accent} strokeWidth="1" opacity="0.3" />
          
          {/* Node 2 */}
          <Circle cx="250" cy="80" r="6" fill={colors.accent} />
          <Circle cx="250" cy="80" r="10" fill="none" stroke={colors.accent} strokeWidth="1" opacity="0.3" />
          
          {/* Node 3 */}
          <Circle cx="230" cy="220" r="6" fill={colors.accent} />
          <Circle cx="230" cy="220" r="10" fill="none" stroke={colors.accent} strokeWidth="1" opacity="0.3" />
          
          {/* Connecting lines */}
          <Path
            d="M50 120 L140 100"
            stroke={colors.accent}
            strokeWidth="1.5"
            strokeDasharray="4,4"
            opacity="0.4"
          />
          <Path
            d="M250 80 L180 100"
            stroke={colors.accent}
            strokeWidth="1.5"
            strokeDasharray="4,4"
            opacity="0.4"
          />
          <Path
            d="M230 220 L170 180"
            stroke={colors.accent}
            strokeWidth="1.5"
            strokeDasharray="4,4"
            opacity="0.4"
          />
        </G>

        {/* Floating icons */}
        {/* Dumbbell icon */}
        <G transform="translate(40, 200)" opacity="0.7">
          <Rect x="0" y="8" width="30" height="4" rx="2" fill={colors.gray[400]} />
          <Circle cx="0" cy="10" r="5" fill={colors.gray[500]} />
          <Circle cx="30" cy="10" r="5" fill={colors.gray[500]} />
        </G>

        {/* Apple/Food icon */}
        <G transform="translate(240, 40)" opacity="0.7">
          <Circle cx="10" cy="15" r="12" fill={colors.secondary} opacity="0.3" />
          <Path
            d="M10 5 Q10 8 8 10"
            stroke={colors.secondary}
            strokeWidth="2"
            strokeLinecap="round"
            fill="none"
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
    paddingVertical: spacing.lg,
  },
});

export default WelcomeIllustration;
