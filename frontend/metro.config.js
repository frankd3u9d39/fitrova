const { getDefaultConfig } = require('expo/metro-config');

const config = getDefaultConfig(__dirname);

const { resolver } = config;

config.resolver = {
  ...resolver,
  assetExts: [...resolver.assetExts, 'glb', 'gltf', 'mtl', 'obj'],
  sourceExts: [...resolver.sourceExts, 'cjs', 'mjs'],
};

module.exports = config;
