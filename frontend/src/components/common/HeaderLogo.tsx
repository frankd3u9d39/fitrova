import React from 'react';
import { View, StyleSheet, Image } from 'react-native';
import { theme } from '../../theme';

export const HeaderLogo = () => {
  return (
    <View style={styles.container}>
      <Image
        source={require('../../../assets/Logo.png')}
        style={styles.logoImage}
        resizeMode="contain"
      />
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: theme.spacing.md,
  },
  logoImage: {
    width: 100,
    height: 100,
    marginRight: theme.spacing.sm,
    marginBottom:-40,
  },
});
