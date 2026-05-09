import mediapipe as mp
try:
    print(f"MediaPipe Version: {mp.__version__}")
    print(f"Solutions available: {dir(mp.solutions) if hasattr(mp, 'solutions') else 'No solutions'}")
    import mediapipe.python.solutions.pose as mp_pose
    print("Successfully imported mediapipe.python.solutions.pose")
except Exception as e:
    print(f"Error: {e}")
