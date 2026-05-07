import React, { Suspense, useEffect, useState } from 'react';
import { View, StyleSheet, ActivityIndicator } from 'react-native';
import { Canvas } from '@react-three/fiber/native';
import { useGLTF, useTexture, OrbitControls, ContactShadows, useAnimations } from '@react-three/drei/native';
import * as THREE from 'three';
import { Asset } from 'expo-asset';

// Sub-component to handle the actual GLTF once URI is resolved
const LoadedModel = ({ uri, textures, exercise }: { uri: string, textures: any, exercise: string }) => {
  const { scene, animations } = useGLTF(uri) as any;
  const { actions } = useAnimations(animations, scene);

  useEffect(() => {
    if (actions && actions[Object.keys(actions)[0]]) {
      // Play the first animation found in the GLB (usually an idle or the workout)
      const actionName = Object.keys(actions)[0];
      actions[actionName]?.reset().fadeIn(0.5).play();
    }
  }, [actions]);

  useEffect(() => {
    if (!scene || !textures) return;

    scene.traverse((child: any) => {
      if (child.isMesh) {
        const name = child.name.toLowerCase();
        const material = new THREE.MeshStandardMaterial({
          roughness: 0.8,
          metalness: 0.1,
        });
        child.material = material;

        if (name.includes('body')) {
          material.map = textures.bodyDiffuse;
          material.normalMap = textures.bodyNormal;
        } else if (name.includes('top') || name.includes('tank')) {
          material.map = textures.topDiffuse;
          material.normalMap = textures.topNormal;
        } else if (name.includes('bottom') || name.includes('short')) {
          material.map = textures.bottomDiffuse;
          material.normalMap = textures.bottomNormal;
        } else if (name.includes('shoe')) {
          material.map = textures.shoesDiffuse;
          material.normalMap = textures.shoesNormal;
        }
        material.needsUpdate = true;
      }
    });
  }, [scene, textures]);

  return (
    <primitive 
      object={scene} 
      scale={2.5} 
      position={[0, -2.5, 0]} 
      rotation={[0, Math.PI, 0]} 
    />
  );
};

const AvatarModel = ({ exercise }: { exercise: string }) => {
  const [localUri, setLocalUri] = useState<string | null>(null);

  useEffect(() => {
    async function load() {
      try {
        const asset = Asset.fromModule(require('../../../assets/workout_character.glb'));
        await asset.downloadAsync();
        setLocalUri(asset.localUri || asset.uri);
      } catch (err) {
        console.error('🤖 [3D]: Error downloading asset:', err);
      }
    }
    load();
  }, []);
  
  // Load textures using standard drei hook
  const textures = useTexture({
    bodyDiffuse: require('../../../assets/man_textures/ManMuscularTankTopWorkoutShorts_Body_diffuse.png'),
    bodyNormal: require('../../../assets/man_textures/ManMuscularTankTopWorkoutShorts_Body_normal.png'),
    topDiffuse: require('../../../assets/man_textures/ManMuscularTankTopWorkoutShorts_Top_diffuse.png'),
    topNormal: require('../../../assets/man_textures/ManMuscularTankTopWorkoutShorts_Top_normal.png'),
    bottomDiffuse: require('../../../assets/man_textures/ManMuscularTankTopWorkoutShorts_Bottom_diffuse.png'),
    bottomNormal: require('../../../assets/man_textures/ManMuscularTankTopWorkoutShorts_Bottom_normal.png'),
    shoesDiffuse: require('../../../assets/man_textures/ManMuscularTankTopWorkoutShorts_Shoes_diffuse.png'),
    shoesNormal: require('../../../assets/man_textures/ManMuscularTankTopWorkoutShorts_Shoes_normal.png'),
  }) as any;

  if (!localUri) return null;

  return <LoadedModel uri={localUri} textures={textures} exercise={exercise} />;
};

export const WorkoutAvatar = ({ exercise }: { exercise: string }) => {
  return (
    <View style={styles.container}>
      <Canvas
        camera={{ position: [0, 1.5, 6], fov: 35 }}
        gl={{ antialias: true }}
      >
        <color attach="background" args={['#F1F5F9']} />
        <ambientLight intensity={1.5} />
        <directionalLight position={[5, 10, 5]} intensity={2} />
        
        <Suspense fallback={null}>
          <AvatarModel exercise={exercise} />
          <ContactShadows 
            position={[0, -2.5, 0]} 
            opacity={0.6} 
            scale={10} 
            blur={2.5} 
            far={4.5} 
          />
        </Suspense>
        
        <OrbitControls enablePan={false} enableZoom={true} minDistance={3} maxDistance={10} />
      </Canvas>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#F1F5F9',
  },
});
