import React, { Suspense, useEffect, useState } from 'react';
import { View, StyleSheet, ActivityIndicator, Text } from 'react-native';
import { Canvas, useFrame } from '@react-three/fiber/native';
import { useGLTF, useTexture, OrbitControls, ContactShadows, useAnimations } from '@react-three/drei/native';
import { Box3, Vector3, MeshStandardMaterial, Color, Quaternion, Euler } from 'three';
import { Asset } from 'expo-asset';
import { MotionData, JOINT_MAP, getInterpolatedPose } from '../../utils/workout/motionUtils';

/**
 * COMPONENT: LoadedModel
 * Handles the actual rendering, auto-framing, and animations of the GLTF scene.
 */
const LoadedModel = ({ uri, textures, exercise, motion, onAnimsLoaded }: { uri: string, textures: any, exercise: string, motion?: MotionData, onAnimsLoaded?: (names: string[]) => void }) => {
  const { scene, animations: gltfAnimations } = useGLTF(uri) as any;
  const animations = gltfAnimations || scene.animations || [];
  const { actions, names } = useAnimations(animations, scene);

  useEffect(() => {
    if (names.length > 0 && onAnimsLoaded) {
      onAnimsLoaded(names);
    }
  }, [names]);

  useEffect(() => {
    if (names.length > 0) {
      console.log('🤖 [3D]: Available Animations:', names);
    } else {
      console.log('🤖 [3D]: No animations found in this model.');
    }
  }, [names]);

  // 1. Compute Bounding Box and Auto-Frame
  useEffect(() => {
    if (!scene) return;

    // Reset transform before calculation
    scene.position.set(0, 0, 0);
    scene.scale.set(1, 1, 1);
    scene.updateMatrixWorld(true);

    // Calculate bounding box in local space
    const box = new Box3().setFromObject(scene);
    const center = new Vector3();
    const size = new Vector3();
    box.getCenter(center);
    box.getSize(size);

    // Scaling logic
    const targetHeight = 3.5;
    const scale = targetHeight / (size.y || 1);

    scene.scale.setScalar(scale);

    // Centering logic
    scene.position.x = -(center.x * scale);
    scene.position.z = -(center.z * scale);
    scene.position.y = -1.75 - (box.min.y * scale);

    // Rigging & Mesh Analysis
    scene.traverse((child: any) => {
      if (child.isMesh) {
        child.castShadow = true;
        child.receiveShadow = true;
        
        const name = child.name.toLowerCase();
        
        let selectedMap = textures.bodyDiffuse;
        let selectedNormal = textures.bodyNormal;

        if (name.includes('top') || name.includes('tank')) {
          selectedMap = textures.topDiffuse;
          selectedNormal = textures.topNormal;
        } else if (name.includes('bottom') || name.includes('short')) {
          selectedMap = textures.bottomDiffuse;
          selectedNormal = textures.bottomNormal;
        } else if (name.includes('shoe')) {
          selectedMap = textures.shoesDiffuse;
          selectedNormal = textures.shoesNormal;
        }

        // REPLACEMENT instead of mutation for better texture reliability
        const newMat = new MeshStandardMaterial({
          map: selectedMap,
          normalMap: selectedNormal,
          roughness: 0.8,
          metalness: 0.1,
          color: 0xffffff,
        });

        // Copy skinning/morph data if present
        if (child.isSkinnedMesh) {
           // In modern Three.js, skinning is automatic for SkinnedMesh
        }
        child.material = newMat;
      }
    });
    
    // Animation Selection & Playback
    let activeActionName = names[0];

    if (names.length > 0) {
      const exerciseLower = exercise.toLowerCase();
      const match = names.find(n => {
        const nLower = n.toLowerCase();
        return nLower.includes(exerciseLower) || 
               exerciseLower.includes(nLower) ||
               (exerciseLower.includes('jack') && nLower.includes('jump'));
      });

      if (match) activeActionName = match;
      else {
        const movements = names.filter(n => {
            const nl = n.toLowerCase();
            return !nl.includes('pose') && !nl.includes('bind') && !nl.includes('idle');
        });
        if (movements.length > 0) activeActionName = movements[0];
      }

    }
    
    // DEBUG: Log all bone names regardless of animations to help with mapping
    const boneNames: string[] = [];
    scene.traverse((node: any) => {
      if (node.isBone) {
        boneNames.push(node.name);
      }
    });
    if (boneNames.length > 0) {
      console.log('🤖 [3D]: Skeleton Bones Found:', boneNames.length, boneNames);
    }
    
    if (motion) {
      console.log('🤖 [3D]: AI Motion data received for:', exercise);
      if (motion.keyframes && motion.keyframes.length > 0) {
        console.log('🤖 [3D]: Pose Keys received:', Object.keys(motion.keyframes[0].joints));
      }
    } else {
      console.log('🤖 [3D]: No AI Motion data. Using fallback wiggle.');
    }

    return () => {
      if (activeActionName) actions[activeActionName]?.fadeOut(0.3);
    };
  }, [scene, animations, actions, names, textures, exercise]);

  // PROCEDURAL AI ANIMATION
  useFrame((state) => {
    if (!motion) {
       // Manual fallback or default bone wiggle if no motion data
       scene.traverse((node: any) => {
         if (node.isBone && (node.name.toLowerCase().includes('arm') || node.name.toLowerCase().includes('hand'))) {
           node.rotation.z = Math.sin(state.clock.elapsedTime * 2) * 0.2;
         }
       });
       return;
    }

    // Calculate loop progress (e.g., 4 second per rep)
    const duration = 4.0;
    const progress = (state.clock.elapsedTime % duration) / duration;
    
    // Get interpolated pose from AI keyframes
    const result = getInterpolatedPose(motion, progress);
    if (!result) return;
    const { joints: pose, position, targets } = result;

    // Apply pose and IK to bones
    scene.traverse((node: any) => {
      if (node.isBone) {
        // Find which joint this bone belongs to
        const jointKey = Object.keys(JOINT_MAP).find(key => 
          JOINT_MAP[key].some(alias => alias.toLowerCase() === node.name.toLowerCase())
        );

          // Apply rotation from pose
          if (pose[jointKey]) {
            const [rx, ry, rz] = pose[jointKey];
            node.rotation.set(rx, ry, rz);
          }
          
          // Apply position if this is the hips/root
          if (jointKey === 'hips' && position) {
             node.position.y = position[1]; 
             node.position.x = position[0];
             node.position.z = position[2];
          }

          // IK TARGET HANDLING
          // If this is a limb root (Shoulder/UpLeg), point it towards the target wrist/ankle
          if (targets) {
            let ikTarget: [number, number, number] | undefined;
            if (jointKey === 'shoulder_l') ikTarget = targets['wrist_l'];
            if (jointKey === 'shoulder_r') ikTarget = targets['wrist_r'];
            if (jointKey === 'leg_l') ikTarget = targets['ankle_l'];
            if (jointKey === 'leg_r') ikTarget = targets['ankle_r'];

            if (ikTarget) {
              const targetVec = new Vector3(ikTarget[0], ikTarget[1], ikTarget[2]);
              // Basic orientation logic: Point the bone towards the target
              // Note: This is a simplified "LookAt" IK for 3D character skeletons
              node.lookAt(targetVec);
              node.rotateX(Math.PI / 2); // Adjust for bone orientation if needed
            }
          }
      }
    });
  });

  return <primitive object={scene} rotation={[0, Math.PI, 0]} />;
};

const ModelWrapper = ({ exercise, motion, onAnimsLoaded }: { exercise: string, motion?: MotionData, onAnimsLoaded?: (names: string[]) => void }) => {
  const [localUri, setLocalUri] = useState<string | null>(null);
  const [loadError, setLoadError] = useState<string | null>(null);

  useEffect(() => {
    async function resolveAsset() {
      try {
        const asset = Asset.fromModule(require('../../../assets/workout_character.glb'));
        await asset.downloadAsync();
        if (!asset.localUri) throw new Error('Failed to resolve localUri');
        setLocalUri(asset.localUri);
      } catch (err: any) {
        console.error('🤖 [3D]: Asset resolution failed:', err);
        setLoadError(err.message);
      }
    }
    resolveAsset();
  }, []);

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

  if (loadError) return <mesh><boxGeometry /><meshStandardMaterial color="red" /></mesh>;
  if (!localUri) return null;

  return <LoadedModel uri={localUri} textures={textures} exercise={exercise} motion={motion} onAnimsLoaded={onAnimsLoaded} />;
};

export const WorkoutAvatar = ({ exercise, motion }: { exercise: string, motion?: MotionData }) => {
  const [isInitializing, setIsInitializing] = useState(true);
  const [anims, setAnims] = useState<string[]>([]);

  useEffect(() => {
    const timer = setTimeout(() => setIsInitializing(false), 800);
    return () => clearTimeout(timer);
  }, []);

  return (
    <View style={styles.container}>
      {isInitializing && (
        <View style={styles.overlay}>
          <ActivityIndicator size="large" color="#10B981" />
          <Text style={styles.loadingText}>Loading 3D Trainer...</Text>
        </View>
      )}

      {anims.length > 0 && (
        <View style={{ position: 'absolute', top: 10, left: 10, zIndex: 20, backgroundColor: 'rgba(0,0,0,0.4)', padding: 4, borderRadius: 4 }}>
          <Text style={{ color: 'white', fontSize: 9 }}>Anims: {anims.join(', ')}</Text>
        </View>
      )}
      
      <Canvas camera={{ position: [0, 0, 5], fov: 45 }} gl={{ antialias: true, alpha: true }}>
        <color attach="background" args={['#F1F5F9']} />
        <ambientLight intensity={1.2} />
        <hemisphereLight intensity={0.8} />
        <directionalLight position={[5, 10, 5]} intensity={1} />
        
        <Suspense fallback={null}>
          <ModelWrapper exercise={exercise} motion={motion} onAnimsLoaded={setAnims} />
          <ContactShadows position={[0, -1.75, 0]} opacity={0.4} scale={10} blur={2} far={4.5} />
        </Suspense>
        
        <OrbitControls enablePan={false} enableZoom={true} target={[0, 0, 0]} />
      </Canvas>
    </View>
  );
};

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#F1F5F9' },
  overlay: { ...StyleSheet.absoluteFillObject, backgroundColor: '#F1F5F9', justifyContent: 'center', alignItems: 'center', zIndex: 10 },
  loadingText: { marginTop: 12, color: '#64748B', fontSize: 14, fontWeight: '600' }
});
