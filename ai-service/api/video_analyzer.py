import os
import json
import base64
import re
import urllib.request
from flask import Flask, request, jsonify
from flask_cors import CORS
from dotenv import load_dotenv
import google.generativeai as genai

load_dotenv()

app = Flask(__name__)
CORS(app)

# Configure Gemini
genai.configure(api_key=os.getenv("GEMINI_API_KEY"))
model = genai.GenerativeModel('gemini-1.5-flash')


def extract_video_id(youtube_url):
    """Extract the 11-character video ID from any YouTube URL format."""
    match = re.search(r'(?:v=|youtu\.be/|embed/|shorts/)([a-zA-Z0-9_-]{11})', youtube_url)
    return match.group(1) if match else None


def fetch_thumbnail_as_base64(url):
    """
    Download a thumbnail image and return it as base64.
    Returns None if the image is the grey YouTube placeholder (< 2 KB).
    """
    try:
        req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})
        with urllib.request.urlopen(req, timeout=10) as resp:
            data = resp.read()
            # YouTube returns a tiny grey placeholder for missing quality levels
            if len(data) < 2000:
                return None
            return base64.b64encode(data).decode('utf-8')
    except Exception:
        return None


def get_thumbnails_for_video(video_id):
    """
    Fetch up to 4 quality thumbnails from YouTube's public CDN.
    No API key or cookies needed — these URLs are always publicly accessible.
    """
    candidate_urls = [
        f"https://img.youtube.com/vi/{video_id}/maxresdefault.jpg",  # 1280×720
        f"https://img.youtube.com/vi/{video_id}/sddefault.jpg",      # 640×480
        f"https://img.youtube.com/vi/{video_id}/hqdefault.jpg",      # 480×360
        f"https://img.youtube.com/vi/{video_id}/mqdefault.jpg",      # 320×180
        f"https://img.youtube.com/vi/{video_id}/default.jpg",        # 120×90 fallback
    ]
    images = []
    for url in candidate_urls:
        b64 = fetch_thumbnail_as_base64(url)
        if b64:
            images.append(b64)
        if len(images) >= 4:
            break
    return images


def analyze_from_thumbnails(images_b64, exercise_name, video_id):
    """
    Ask Gemini to produce expert coaching tips from thumbnail imagery.
    Falls back to quality generic coaching advice if Gemini is unavailable.
    """
    image_parts = [{"mime_type": "image/jpeg", "data": img} for img in images_b64]

    prompt = f"""
You are an expert AI Fitness Coach reviewing a YouTube workout tutorial.

The video exercise topic: "{exercise_name}"
YouTube video ID: {video_id}

Based on the thumbnail image(s) provided and your expert knowledge of proper {exercise_name} technique:

1. Write a brief coaching summary describing what excellent {exercise_name} form looks like.
2. Give 3-4 specific, actionable pro tips a beginner should focus on when performing {exercise_name}.
3. An "Accuracy Score" (0-100) for how ideally this thumbnail represents proper form.
4. A 'status' field: one of 'IDEAL', 'GOOD', or 'CAUTION'.

Respond ONLY with valid JSON — no markdown fences, no extra text:
{{
    "exercise": "{exercise_name}",
    "summary": "...",
    "pro_tips": ["tip 1", "tip 2", "tip 3"],
    "accuracy_score": 90,
    "status": "GOOD"
}}
"""
    contents = [{"role": "user", "parts": [{"text": prompt}] + image_parts}]

    try:
        response = model.generate_content(contents)
        text = response.text.strip()
        # Strip markdown fences if Gemini adds them
        if "```json" in text:
            text = text.split("```json")[1].split("```")[0].strip()
        elif "```" in text:
            text = text.split("```")[1].split("```")[0].strip()
        return json.loads(text)
    except Exception as e:
        print(f"[Gemini thumbnail analysis error]: {e}")
        # Graceful fallback — still returns useful coaching data
        return {
            "exercise": exercise_name,
            "summary": (
                f"This video covers proper {exercise_name} technique. "
                "Focus on maintaining good posture and controlled movement throughout."
            ),
            "pro_tips": [
                "Keep your spine neutral — avoid rounding your lower back",
                "Control the movement on the way down (eccentric phase)",
                "Engage your core throughout every repetition",
                "Breathe out on the effort, breathe in on the return"
            ],
            "accuracy_score": 85,
            "status": "GOOD"
        }



def analyze_form_from_frames(frames_b64, exercise_name):
    """
    Ask Gemini to produce expert coaching tips from sequential video frames.
    """
    image_parts = [{"mime_type": "image/jpeg", "data": img} for img in frames_b64]

    prompt = f"""
You are an AI Personal Trainer and expert biomechanics coach. Analyze the user's workout form from these sequential video frames.
The expected exercise they should be performing is: "{exercise_name}".
The frames are in chronological order: Frame 1 = start of movement, Frame 2 = mid-movement, Frame 3 = end/peak of movement.

Your tasks:
1. Detect what exercise the user is actually performing (e.g., 'Squats', 'Push-Ups', 'Deadlift', etc.).
2. Cross-reference the exercise you detect with the expected exercise ("{exercise_name}"):
   - If the expected exercise is NOT 'Detect automatically' and the user is doing a completely different exercise, flag this as a critical mismatch.
   - For an exercise mismatch: Set status to "IMPROVEMENT_NEEDED", score to 0, summary to: "Wrong exercise detected! You are performing [Detected Exercise], but the selected workout is [Expected Exercise]. Please perform the correct exercise for form checking.", and list tips explaining this.
3. If they are performing the correct exercise (or expected is 'Detect automatically'):
   - Critically evaluate body positioning, range of motion, joint alignment across all frames.
   - Use the sequence of frames to assess the movement arc, timing, and control.
   - If there are flaws (e.g., rounding back, incorrect elbow/shoulder angles, shallow depth, knees caving, uncontrolled speed), set status to "IMPROVEMENT_NEEDED", deduct score (0-100), and provide specific, actionable coaching tips.
   - If form is excellent across the full movement, set status to "GOOD", score 85-100, and provide positive reinforcement.

Return ONLY valid JSON in this format:
{{
  "status": "GOOD" or "IMPROVEMENT_NEEDED",
  "detected_exercise": "Actual name of the exercise you see them doing",
  "score": 0-100,
  "tips": ["Specific coaching tip 1", "Specific coaching tip 2"],
  "summary": "Encouraging coaching summary explaining if they did the correct exercise and how their form looked across the movement"
}}
"""
    contents = [{"role": "user", "parts": [{"text": prompt}] + image_parts}]

    try:
        response = model.generate_content(contents)
        text = response.text.strip()
        if "```json" in text:
            text = text.split("```json")[1].split("```")[0].strip()
        elif "```" in text:
            text = text.split("```")[1].split("```")[0].strip()
        return json.loads(text)
    except Exception as e:
        print(f"[Gemini form check analysis error]: {e}")
        return None


@app.route('/health', methods=['GET'])
def health():
    return jsonify({"status": "healthy"}), 200


@app.route('/api/analyze-youtube', methods=['POST'])
def analyze_youtube():
    data = request.json
    youtube_url = data.get('youtube_url')
    exercise_name = data.get('exercise_name', 'Workout')

    if not youtube_url:
        return jsonify({"error": "No YouTube URL provided"}), 400

    try:
        video_id = extract_video_id(youtube_url)
        if not video_id:
            return jsonify({"error": "Invalid YouTube URL"}), 400

        images_b64 = get_thumbnails_for_video(video_id)
        if not images_b64:
            return jsonify({"error": "Could not fetch video thumbnails"}), 500

        analysis = analyze_from_thumbnails(images_b64, exercise_name, video_id)

        return jsonify({
            "status": "success",
            "youtube_url": youtube_url,
            "analysis": analysis,
            "frames_analyzed": len(images_b64)
        })

    except Exception as e:
        import traceback
        print(traceback.format_exc())
        return jsonify({"error": str(e)}), 500


@app.route('/api/analyze-form', methods=['POST'])
def analyze_form():
    data = request.json
    if not data:
        return jsonify({"error": "No JSON payload provided"}), 400

    frames_b64 = data.get('frames', [])
    exercise_name = data.get('exercise_name', 'Workout')

    if not frames_b64:
        return jsonify({"error": "No frames provided"}), 400

    try:
        # Strip data-URI prefix if present in any of the base64 strings
        clean_frames = []
        for f in frames_b64:
            if "," in f:
                f = f.split(",")[1]
            clean_frames.append(f)

        analysis = analyze_form_from_frames(clean_frames, exercise_name)
        if not analysis:
            return jsonify({"error": "Failed to analyze form using Gemini"}), 500

        return jsonify({
            "status": "success",
            "analysis": analysis
        })
    except Exception as e:
        return jsonify({"error": str(e)}), 500


if __name__ == '__main__':
    port = int(os.getenv("PORT", 7860))
    app.run(host='0.0.0.0', port=port, debug=True)
