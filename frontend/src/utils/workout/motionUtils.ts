import { Euler, Quaternion, MathUtils } from 'three';

export interface JointKeyframe {
  /** 0.0 to 1.0 representing progress through the exercise rep */
  time: number; 
  /** Optional: Human-readable joint names to Euler rotations [x, y, z] */
  joints?: {
    [jointName: string]: [number, number, number];
  };
  /** Preferred: IK Targets for hands/feet [x, y, z] relative to hips */
  targets?: {
    [targetName: string]: [number, number, number];
  };
  /** Optional root position offset [x, y, z] */
  position?: [number, number, number];
}

export interface MotionData {
  keyframes: JointKeyframe[];
}

/**
 * JOINT_MAP
 * Maps human-readable names used by AI to standard bone names.
 * We include multiple aliases to increase compatibility with different models.
 */
export const JOINT_MAP: { [key: string]: string[] } = {
  hips: ['mixamorigHips', 'Hips', 'mixamorig_Hips', 'pelvis'],
  spine: ['mixamorigSpine', 'Spine', 'mixamorig_Spine', 'spine'],
  neck: ['mixamorigNeck', 'Neck', 'mixamorig_Neck', 'neck'],
  head: ['mixamorigHead', 'Head', 'mixamorig_Head', 'head'],
  
  shoulder_l: ['mixamorigLeftArm', 'LeftShoulder', 'mixamorig_LeftShoulder', 'shoulder_L'],
  shoulder_r: ['mixamorigRightArm', 'RightShoulder', 'mixamorig_RightShoulder', 'shoulder_R'],
  
  elbow_l: ['mixamorigLeftForeArm', 'LeftForeArm', 'mixamorig_LeftForeArm', 'elbow_L'],
  elbow_r: ['mixamorigRightForeArm', 'RightForeArm', 'mixamorig_RightForeArm', 'elbow_R'],
  
  wrist_l: ['mixamorigLeftHand', 'LeftHand', 'mixamorig_LeftHand', 'wrist_L'],
  wrist_r: ['mixamorigRightHand', 'RightHand', 'mixamorig_RightHand', 'wrist_R'],
  
  leg_l: ['mixamorigLeftUpLeg', 'LeftUpLeg', 'mixamorig_LeftUpLeg'],
  leg_r: ['mixamorigRightUpLeg', 'RightUpLeg', 'mixamorig_RightUpLeg'],
  
  knee_l: ['mixamorigLeftLeg', 'LeftLeg', 'mixamorig_LeftLeg', 'knee_L'],
  knee_r: ['mixamorigRightLeg', 'RightLeg', 'mixamorig_RightLeg', 'knee_R'],
  
  ankle_l: ['mixamorigLeftFoot', 'LeftFoot', 'mixamorig_LeftFoot', 'ankle_L'],
  ankle_r: ['mixamorigRightFoot', 'RightFoot', 'mixamorig_RightFoot', 'ankle_R'],
};

/**
 * Interpolates between two keyframes based on a normalized time (0-1).
 */
export function getInterpolatedPose(motion: MotionData, progress: number) {
  if (!motion.keyframes || motion.keyframes.length === 0) return null;

  // Handle boundary cases
  if (progress <= motion.keyframes[0].time) return motion.keyframes[0].joints;
  if (progress >= motion.keyframes[motion.keyframes.length - 1].time) 
    return motion.keyframes[motion.keyframes.length - 1].joints;

  // Find the two keyframes to interpolate between
  let i = 0;
  while (i < motion.keyframes.length - 1 && motion.keyframes[i + 1].time < progress) {
    i++;
  }

  const k1 = motion.keyframes[i];
  const k2 = motion.keyframes[i + 1];

  // Calculate local interpolation factor (0-1)
  const t = (progress - k1.time) / (k2.time - k1.time);

  const result: { [key: string]: [number, number, number] } = {};
  let position: [number, number, number] | undefined = undefined;

  // Interpolate each joint
  for (const joint in k1.joints) {
    if (k2.joints[joint]) {
      const r1 = k1.joints[joint];
      const r2 = k2.joints[joint];
      
      result[joint] = [
        MathUtils.lerp(r1[0], r2[0], t),
        MathUtils.lerp(r1[1], r2[1], t),
        MathUtils.lerp(r1[2], r2[2], t)
      ];
    } else {
      result[joint] = k1.joints[joint];
    }
  }

  // Interpolate position if present
  if (k1.position && k2.position) {
    position = [
      MathUtils.lerp(k1.position[0], k2.position[0], t),
      MathUtils.lerp(k1.position[1], k2.position[1], t),
      MathUtils.lerp(k1.position[2], k2.position[2], t)
    ];
  } else if (k1.position) {
    position = k1.position;
  }

  // Interpolate IK targets if present
  const targets: { [key: string]: [number, number, number] } = {};
  if (k1.targets && k2.targets) {
    for (const key in k1.targets) {
      if (k2.targets[key]) {
        const p1 = k1.targets[key];
        const p2 = k2.targets[key];
        targets[key] = [
          MathUtils.lerp(p1[0], p2[0], t),
          MathUtils.lerp(p1[1], p2[1], t),
          MathUtils.lerp(p1[2], p2[2], t)
        ];
      }
    }
  }

  return { joints: result, position, targets };
}

/**
 * SOLVE_IK
 * A simple 2-joint analytic IK solver for human limbs (Shoulder -> Elbow -> Wrist).
 * @param target Local target position relative to the root joint (Shoulder/Hip)
 * @param a Length of the upper segment
 * @param b Length of the lower segment
 */
export function solveIK(target: [number, number, number], a: number, b: number) {
  const [tx, ty, tz] = target;
  const d = Math.sqrt(tx * tx + ty * ty + tz * tz);
  
  // Clamp d to avoid NaN if target is out of reach
  const dist = Math.max(0.1, Math.min(a + b - 0.01, d));

  // Law of Cosines to find the angle at the elbow (interior angle)
  // cos(C) = (a^2 + b^2 - d^2) / (2ab)
  const cosC = (a * a + b * b - dist * dist) / (2 * a * b);
  const elbowAngle = Math.acos(Math.max(-1, Math.min(1, cosC)));

  // This returns the rotations for the upper and lower joints
  // In a real rig, you'd also need the orientation towards the target
  return {
    upper: [0, 0, 0] as [number, number, number], // Rotation towards target
    lower: [0, 0, Math.PI - elbowAngle] as [number, number, number], // Bending angle
  };
}
