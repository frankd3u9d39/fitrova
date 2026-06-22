<?php
/**
 * Fitrova - AI Fitness Coach Landing Page
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fitrova — Your AI Fitness Coach</title>
    <meta name="description" content="Transform your fitness journey with Fitrova, your AI-powered coach. Personalized workouts, intelligent nutrition, and real-time form analysis.">
    <link rel="icon" type="image/png" href="/favicon.png">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Styles -->
    <style>
        :root {
            --primary: #13ec13;
            --primary-glow: rgba(19, 236, 19, 0.25);
            --primary-glow-heavy: rgba(19, 236, 19, 0.5);
            --bg: #070a12;
            --surface: #0d1321;
            --surface-hover: #151e33;
            --border: rgba(255, 255, 255, 0.08);
            --border-hover: rgba(19, 236, 19, 0.3);
            --text: #f8faf8;
            --muted: #94a3b8;
            --card-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.7);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            scroll-behavior: smooth;
        }

        body {
            background-color: var(--bg);
            color: var(--text);
            font-family: 'Manrope', sans-serif;
            overflow-x: hidden;
            line-height: 1.6;
        }

        /* Background Glows */
        .glow-bg {
            position: absolute;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, var(--primary-glow) 0%, rgba(7, 10, 18, 0) 70%);
            top: -200px;
            right: -200px;
            z-index: 0;
            pointer-events: none;
            filter: blur(80px);
        }

        .glow-bg-left {
            position: absolute;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(19, 236, 19, 0.08) 0%, rgba(7, 10, 18, 0) 70%);
            top: 600px;
            left: -200px;
            z-index: 0;
            pointer-events: none;
            filter: blur(60px);
        }

        /* Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            position: relative;
            z-index: 1;
        }

        /* Navbar */
        nav {
            padding: 1.5rem 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border);
            position: relative;
            z-index: 10;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: 800;
            letter-spacing: -0.04em;
            text-decoration: none;
            color: var(--text);
            display: flex;
            align-items: center;
        }

        .logo span {
            color: var(--primary);
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .nav-links a {
            color: var(--muted);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            transition: color 0.3s ease;
        }

        .nav-links a:hover {
            color: var(--text);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background-color: var(--primary);
            color: #000;
            font-weight: 700;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 4px 15px var(--primary-glow);
            border: 1px solid transparent;
            font-size: 0.95rem;
            cursor: pointer;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px var(--primary-glow-heavy);
            background-color: #1cfc1c;
        }

        .btn-outline {
            background-color: transparent;
            color: var(--text);
            border: 1px solid var(--border);
            box-shadow: none;
        }

        .btn-outline:hover {
            background-color: var(--surface);
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-2px);
        }

        /* Hero Section */
        header.hero {
            padding: 6rem 0 4rem;
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 4rem;
            align-items: center;
        }

        .hero-content h1 {
            font-size: 3.8rem;
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: -0.04em;
            margin-bottom: 1.5rem;
            animation: fadeInUp 0.8s ease;
        }

        .hero-content h1 span {
            background: linear-gradient(135deg, #13ec13 0%, #00ffcc 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-content p {
            font-size: 1.15rem;
            color: var(--muted);
            margin-bottom: 2.5rem;
            line-height: 1.7;
            max-width: 520px;
            animation: fadeInUp 1s ease;
        }

        .hero-ctas {
            display: flex;
            gap: 1rem;
            margin-bottom: 3rem;
            animation: fadeInUp 1.2s ease;
        }

        /* Stats Grid */
        .hero-badges {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
            animation: fadeInUp 1.4s ease;
        }

        .hero-badge {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border);
            padding: 0.5rem 1rem;
            border-radius: 99px;
            font-size: 0.85rem;
            color: var(--muted);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }


        /* Mockup Mobile UI */
        .hero-mockup-wrapper {
            position: relative;
            animation: float 6s ease-in-out infinite;
        }

        .phone-mockup-img {
            width: 100%;
            max-width: 300px;
            display: block;
            margin: 0 auto;
            border-radius: 44px;
            box-shadow: var(--card-shadow), 0 0 50px rgba(19, 236, 19, 0.12);
        }

        /* Floating badges */
        .floating-feature-badge {
            position: absolute;
            background: rgba(13, 19, 33, 0.88);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border);
            padding: 0.6rem 0.9rem;
            border-radius: 14px;
            font-size: 0.75rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            white-space: nowrap;
        }
        .badge-1 {
            top: 18%;
            left: -50px;
            border-color: rgba(19, 236, 19, 0.3);
        }
        .badge-2 {
            bottom: 18%;
            right: -44px;
            border-color: rgba(0, 255, 204, 0.3);
        }
        .floating-feature-badge i {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .badge-1 i { background-color: var(--primary); box-shadow: 0 0 8px var(--primary); }
        .badge-2 i { background-color: #00ffcc; box-shadow: 0 0 8px #00ffcc; }

        /* Notch */
        .phone-mockup::before {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 22px;
            background: #222;
            border-bottom-left-radius: 14px;
            border-bottom-right-radius: 14px;
            z-index: 20;
        }

        .pm-inner {
            padding: 2rem 1rem 0;
            background: #f5f5f7;
            position: relative;
        }

        /* Greeting */
        .pm-greeting {
            margin-bottom: 1.1rem;
            margin-top: 0.4rem;
        }
        .pm-greeting h4 {
            font-size: 1rem;
            font-weight: 700;
            color: #111;
            margin-bottom: 0.15rem;
        }
        .pm-greeting h4 span { color: var(--primary); }
        .pm-greeting p {
            font-size: 0.7rem;
            color: #888;
            font-weight: 500;
        }
        .pm-top-row {
            display: flex;
            gap: 0.6rem;
            margin-bottom: 0.9rem;
        }

        /* AI Health Score Card */
        .pm-card {
            background: #fff;
            border-radius: 18px;
            padding: 0.9rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            flex: 1;
        }
        .pm-card-label {
            font-size: 0.58rem;
            font-weight: 700;
            color: #aaa;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 0.6rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .pm-card-label span { font-size: 0.85rem; }
        .pm-score-row {
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .pm-ring-wrap {
            position: relative;
            width: 52px;
            height: 52px;
            flex-shrink: 0;
        }
        .pm-ring-wrap svg { display: block; }
        .pm-ring-bg {
            fill: none;
            stroke: #eee;
            stroke-width: 5;
        }
        .pm-ring-val {
            fill: none;
            stroke: var(--primary);
            stroke-width: 5;
            stroke-dasharray: 138;
            stroke-dashoffset: 35;
            stroke-linecap: round;
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
        }
        .pm-ring-num {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            font-weight: 800;
            color: #111;
        }
        .pm-score-info {
            display: flex;
            flex-direction: column;
        }
        .pm-score-pts {
            font-size: 0.65rem;
            font-weight: 700;
            color: var(--primary);
        }
        .pm-score-sub {
            font-size: 0.6rem;
            color: #aaa;
        }

        /* Calories card */
        .pm-cal-card {
            background: #fff;
            border-radius: 18px;
            padding: 0.9rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .pm-cal-card .pm-card-label {
            width: 100%;
            justify-content: center;
            margin-bottom: 0.4rem;
        }
        .pm-fire { font-size: 1.4rem; margin-bottom: 0.2rem; }
        .pm-cal-num {
            font-size: 1.3rem;
            font-weight: 800;
            color: #111;
            line-height: 1;
        }
        .pm-cal-sub {
            font-size: 0.6rem;
            color: #aaa;
            margin-top: 0.15rem;
        }

        /* Workout card */
        .pm-workout-card {
            background: #162d24;
            border-radius: 20px;
            padding: 1rem 1.1rem;
            margin-bottom: 0.9rem;
        }
        .pm-workout-label {
            font-size: 0.58rem;
            font-weight: 700;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 0.09em;
            margin-bottom: 0.35rem;
        }
        .pm-workout-row {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
        }
        .pm-workout-title {
            font-size: 0.9rem;
            font-weight: 800;
            color: #fff;
            line-height: 1.25;
            max-width: 140px;
        }
        .pm-workout-time {
            background: rgba(255,255,255,0.12);
            border-radius: 99px;
            padding: 0.3rem 0.7rem;
            font-size: 0.65rem;
            font-weight: 700;
            color: #ddd;
            display: flex;
            align-items: center;
            gap: 0.3rem;
            white-space: nowrap;
        }

        /* Weight trend */
        .pm-weight-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 0.1rem;
            margin-bottom: 0.7rem;
        }
        .pm-weight-label {
            font-size: 0.6rem;
            font-weight: 700;
            color: #aaa;
            text-transform: uppercase;
            letter-spacing: 0.07em;
        }
        .pm-weight-val {
            font-size: 1rem;
            font-weight: 800;
            color: #111;
        }
        .pm-weight-val span { font-size: 0.65rem; color: #888; font-weight: 500; }

        /* Sparkline placeholder */
        .pm-sparkline {
            height: 36px;
            margin: 0 0.1rem 0.8rem;
            position: relative;
        }
        .pm-sparkline svg { width: 100%; height: 100%; }

        /* Day row */
        .pm-days {
            display: flex;
            justify-content: space-between;
            padding: 0 0.2rem;
            margin-bottom: 0.8rem;
        }
        .pm-day {
            font-size: 0.6rem;
            font-weight: 600;
            color: #bbb;
            text-align: center;
        }
        .pm-day.active {
            color: var(--primary);
            font-weight: 800;
        }

        /* Bottom nav */
        .pm-nav {
            background: #111;
            border-radius: 0 0 36px 36px;
            padding: 0.7rem 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
        }
        .pm-nav-home {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            position: absolute;
            left: 50%;
            transform: translateX(-50%) translateY(-40%);
            box-shadow: 0 0 14px rgba(19,236,19,0.5);
        }
        .pm-nav-icon {
            font-size: 1rem;
            color: #555;
            padding: 0.3rem;
        }
        .pm-nav-right {
            display: flex;
            gap: 1.8rem;
        }
        .pm-nav-left { width: 44px; }

        /* Floating badges */
        .floating-feature-badge {
            position: absolute;
            background: rgba(13, 19, 33, 0.88);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border);
            padding: 0.6rem 0.9rem;
            border-radius: 14px;
            font-size: 0.75rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            white-space: nowrap;
        }
        .badge-1 {
            top: 18%;
            left: -50px;
            border-color: rgba(19, 236, 19, 0.3);
        }
        .badge-2 {
            bottom: 18%;
            right: -44px;
            border-color: rgba(0, 255, 204, 0.3);
        }
        .floating-feature-badge i {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .badge-1 i { background-color: var(--primary); box-shadow: 0 0 8px var(--primary); }
        .badge-2 i { background-color: #00ffcc; box-shadow: 0 0 8px #00ffcc; }


        /* Features Section */
        .features-section {
            padding: 6rem 0;
            position: relative;
        }

        .section-header {
            text-align: center;
            margin-bottom: 4rem;
        }

        .section-header h2 {
            font-size: 2.5rem;
            font-weight: 800;
            letter-spacing: -0.04em;
            margin-bottom: 1rem;
        }

        .section-header h2 span {
            color: var(--primary);
        }

        .section-header p {
            color: var(--muted);
            max-width: 600px;
            margin: 0 auto;
            font-size: 1.05rem;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 2rem;
        }

        .feature-card {
            background-color: var(--surface);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 2.5rem 2rem;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at top left, rgba(19, 236, 19, 0.08) 0%, rgba(0,0,0,0) 50%);
            opacity: 0;
            transition: opacity 0.5s ease;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            border-color: var(--border-hover);
            box-shadow: var(--card-shadow), 0 5px 20px rgba(19, 236, 19, 0.03);
        }

        .feature-card:hover::before {
            opacity: 1;
        }

        .feature-icon-wrapper {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            background-color: rgba(19, 236, 19, 0.06);
            border: 1px solid rgba(19, 236, 19, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--primary);
            margin-bottom: 1.75rem;
            transition: all 0.3s ease;
            position: relative;
            z-index: 2;
        }

        .feature-card:hover .feature-icon-wrapper {
            background-color: var(--primary);
            color: #000;
            transform: scale(1.05);
            box-shadow: 0 0 15px rgba(19, 236, 19, 0.3);
        }

        .feature-card h3 {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            position: relative;
            z-index: 2;
        }

        .feature-card p {
            color: var(--muted);
            font-size: 0.95rem;
            line-height: 1.6;
            position: relative;
            z-index: 2;
        }

        /* Interactive Showcase */
        .showcase-section {
            padding: 4rem 0;
        }

        .showcase-container {
            background: linear-gradient(135deg, #0a0f1d 0%, #080c16 100%);
            border: 1px solid var(--border);
            border-radius: 32px;
            padding: 4rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
        }

        .showcase-content h2 {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 1.25rem;
            letter-spacing: -0.03em;
        }

        .showcase-content p {
            color: var(--muted);
            margin-bottom: 2rem;
            font-size: 1.05rem;
        }

        .showcase-tabs {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .showcase-tab {
            background-color: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border);
            padding: 1.25rem;
            border-radius: 20px;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .showcase-tab:hover {
            background-color: rgba(255, 255, 255, 0.04);
            border-color: rgba(255, 255, 255, 0.15);
        }

        .showcase-tab.active {
            background-color: var(--surface);
            border-color: var(--primary);
            box-shadow: 0 0 15px rgba(19, 236, 19, 0.05);
        }

        .tab-num {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background-color: rgba(255,255,255,0.06);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--muted);
            transition: all 0.3s ease;
        }

        .showcase-tab.active .tab-num {
            background-color: var(--primary);
            color: #000;
        }

        .tab-text h4 {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            transition: color 0.3s ease;
        }

        .showcase-tab.active .tab-text h4 {
            color: var(--primary);
        }

        .tab-text p {
            margin: 0;
            font-size: 0.85rem;
            color: var(--muted);
        }

        .showcase-visuals {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 420px;
            position: relative;
        }

        .visual-display {
            width: 100%;
            height: 100%;
            border-radius: 24px;
            border: 1px solid var(--border);
            background-color: var(--surface);
            padding: 2rem;
            display: none;
            flex-direction: column;
            justify-content: center;
            animation: fadeIn 0.5s ease;
        }

        .visual-display.active {
            display: flex;
        }

        .visual-item-bar {
            height: 8px;
            background: rgba(255,255,255,0.05);
            border-radius: 4px;
            margin-top: 0.5rem;
            overflow: hidden;
        }

        .visual-item-fill {
            height: 100%;
            background: var(--primary);
            width: 0%;
            transition: width 1s ease;
        }

        /* Download Section */
        .download-section {
            padding: 6rem 0;
            text-align: center;
        }

        .download-card {
            background: radial-gradient(circle at top right, rgba(19, 236, 19, 0.12) 0%, rgba(13, 19, 33, 0) 60%), var(--surface);
            border: 1px solid var(--border);
            border-radius: 36px;
            padding: 5rem 2rem;
            max-width: 900px;
            margin: 0 auto;
            box-shadow: var(--card-shadow);
            position: relative;
            overflow: hidden;
        }

        .download-card::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 60%;
            height: 2px;
            background: linear-gradient(90deg, transparent 0%, var(--primary) 50%, transparent 100%);
        }

        .download-card h2 {
            font-size: 2.8rem;
            font-weight: 800;
            letter-spacing: -0.04em;
            margin-bottom: 1.25rem;
        }

        .download-card p {
            color: var(--muted);
            max-width: 540px;
            margin: 0 auto 2.5rem;
            font-size: 1.05rem;
        }

        .download-actions {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            flex-wrap: wrap;
            margin-bottom: 2rem;
        }

        .download-meta {
            font-size: 0.85rem;
            color: var(--muted);
            font-weight: 600;
            display: flex;
            justify-content: center;
            gap: 2rem;
        }

        .download-meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }


        /* Footer */
        footer {
            border-top: 1px solid var(--border);
            padding: 4rem 0;
            color: var(--muted);
            font-size: 0.9rem;
            background-color: #04060c;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 1.5fr repeat(3, 1fr);
            gap: 4rem;
            margin-bottom: 4rem;
        }

        .footer-brand h3 {
            font-size: 1.6rem;
            font-weight: 800;
            letter-spacing: -0.04em;
            color: var(--text);
            margin-bottom: 1rem;
        }

        .footer-brand h3 span {
            color: var(--primary);
        }

        .footer-brand p {
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        .footer-col h4 {
            color: var(--text);
            font-weight: 700;
            margin-bottom: 1.25rem;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .footer-col ul {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .footer-col ul a {
            color: var(--muted);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-col ul a:hover {
            color: var(--primary);
        }

        .footer-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid var(--border);
            padding-top: 2rem;
            font-size: 0.8rem;
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-12px); }
            100% { transform: translateY(0px); }
        }

        /* Responsive Layouts */
        @media (max-width: 991px) {
            header.hero {
                grid-template-columns: 1fr;
                gap: 3rem;
                padding: 4rem 0;
                text-align: center;
            }

            .hero-content p {
                margin-left: auto;
                margin-right: auto;
            }

            .hero-ctas {
                justify-content: center;
            }

            .hero-badges {
                justify-content: center;
            }

            .showcase-container {
                grid-template-columns: 1fr;
                gap: 3rem;
                padding: 2.5rem;
            }

            .showcase-visuals {
                height: 340px;
                order: -1;
            }

            .footer-grid {
                grid-template-columns: 1fr 1fr;
                gap: 2.5rem;
            }
        }

        @media (max-width: 600px) {
            .nav-links {
                display: none; /* Mobile menu shortcut */
            }

            .hero-content h1 {
                font-size: 2.5rem;
            }

            .download-card h2 {
                font-size: 2rem;
            }

            .footer-grid {
                grid-template-columns: 1fr;
            }

            .footer-bottom {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
        }
    </style>
</head>
<body>

    <!-- Ambient Lights -->
    <div class="glow-bg"></div>
    <div class="glow-bg-left"></div>

    <div class="container">
        <!-- Navigation -->
        <nav>
            <a href="#" class="logo" id="navLogo">Fitrova<span>.</span></a>
            <div class="nav-links">
                <a href="#features">Features</a>
                <a href="#showcase">Inside the App</a>
                <a href="/privacy.php">Privacy Policy</a>
                <a href="#download" class="btn btn-outline" style="padding: 0.5rem 1.25rem;">Get App</a>
            </div>
        </nav>

        <!-- Hero Section -->
        <header class="hero">
            <div class="hero-content">
                <h1>Your <span>AI-Powered</span><br>Fitness Coach</h1>
                <p>Fitrova combines advanced computer vision, state-of-the-art LLMs, and metabolic calculations to bring high-performance athletic coaching straight to your mobile device.</p>
                
                <div class="hero-ctas">
                    <a href="/Fitrova.apk" class="btn" id="heroDownloadBtn" download="Fitrova.apk">Download APK</a>
                    <a href="#features" class="btn btn-outline">Explore Features</a>
                </div>

                <div class="hero-badges">
                    <div class="hero-badge">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="color:var(--primary);flex-shrink:0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                        </svg>
                        AI Personal Coach
                    </div>
                    <div class="hero-badge">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="color:var(--primary);flex-shrink:0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Smart Nutrition Scan
                    </div>
                    <div class="hero-badge">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="color:var(--primary);flex-shrink:0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        Live Form Analysis
                    </div>
                </div>
            </div>

            <!-- Dashboard Mockup: Momo Screenshot -->
            <div class="hero-mockup-wrapper">
                <img
                    src="/home.jpg"
                    alt="Fitrova app dashboard"
                    class="phone-mockup-img"
                >

                <!-- Floating tags -->
                <div class="floating-feature-badge badge-1">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="#13ec13" style="flex-shrink:0">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Meal Scan Active
                </div>
                <div class="floating-feature-badge badge-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="#00ffcc" style="flex-shrink:0">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 3.75H6A2.25 2.25 0 003.75 6v1.5M16.5 3.75H18A2.25 2.25 0 0120.25 6v1.5m0 9V18A2.25 2.25 0 0118 20.25h-1.5m-9 0H6A2.25 2.25 0 013.75 18v-1.5M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Squat Tracker 98%
                </div>
            </div>
        </header>

        <!-- Features Section -->
        <section class="features-section" id="features">
            <div class="section-header">
                <h2>Intelligent Features for <span>Peak Results</span></h2>
                <p>Fitrova strips away the guesswork. By leveraging deep learning and tailored tracking, we enable smart adjustments for your fitness goals.</p>
            </div>

            <div class="features-grid">
                <!-- AI workouts -->
                <div class="feature-card">
                    <div class="feature-icon-wrapper">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="28" height="28">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                        </svg>
                    </div>
                    <h3>AI Workout Generation</h3>
                    <p>Get dynamic workout routines generated on-demand by state-of-the-art language models. Tailored to your fitness levels, equipment availability, and target muscle groups.</p>
                </div>

                <!-- Form analysis -->
                <div class="feature-card">
                    <div class="feature-icon-wrapper">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="28" height="28">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 3.75H6A2.25 2.25 0 003.75 6v1.5M16.5 3.75H18A2.25 2.25 0 0120.25 6v1.5m0 9V18A2.25 2.25 0 0118 20.25h-1.5m-9 0H6A2.25 2.25 0 013.75 18v-1.5M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <h3>Pose & Form Tracking</h3>
                    <p>Protect your joints and build solid posture. Fitrova's computer vision engine evaluates critical angles of exercises like squats, providing immediate visual hints.</p>
                </div>

                <!-- Nutrition -->
                <div class="feature-card">
                    <div class="feature-icon-wrapper">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="28" height="28">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
                        </svg>
                    </div>
                    <h3>Smart Nutrition Analyzer</h3>
                    <p>Effortlessly log your food entries and instantly receive complete macronutrient and micronutrient profiles, allowing you to fine-tune your caloric goals.</p>
                </div>

                <!-- Community -->
                <div class="feature-card">
                    <div class="feature-icon-wrapper">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="28" height="28">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                        </svg>
                    </div>
                    <h3>Athlete Connections</h3>
                    <p>Share logs, participate in active monthly workout challenges, climb the regional leaderboard, and follow your workout partners to stay accountable.</p>
                </div>
            </div>
        </section>

        <!-- Showcase Section -->
        <section class="showcase-section" id="showcase">
            <div class="showcase-container">
                <div class="showcase-content">
                    <h2>Experience <span>Fitrova</span></h2>
                    <p>Our dashboard displays elegant trackers that make understanding health data intuitive.</p>
                    
                    <div class="showcase-tabs">
                        <div class="showcase-tab active" onclick="switchTab(0)">
                            <div class="tab-num">1</div>
                            <div class="tab-text">
                                <h4>AI Diet Coach</h4>
                                <p>Get instantaneous calorie target updates and tailored high-protein recommendations.</p>
                            </div>
                        </div>
                        <div class="showcase-tab" onclick="switchTab(1)">
                            <div class="tab-num">2</div>
                            <div class="tab-text">
                                <h4>Form Correction Analyzer</h4>
                                <p>Interactive camera feedback showing exercise correctness rating.</p>
                            </div>
                        </div>
                        <div class="showcase-tab" onclick="switchTab(2)">
                            <div class="tab-num">3</div>
                            <div class="tab-text">
                                <h4>Progress Hub</h4>
                                <p>Keep track of weekly metrics, lean muscle mass growth, and cardio limits.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="showcase-visuals">
                    <!-- Tab 1 Visual -->
                    <div class="visual-display active" id="tab-visual-0">
                        <h4 style="margin-bottom: 1rem; color: var(--primary);">Intelligent Meal Log</h4>
                        <p style="font-size: 0.9rem; color: var(--muted); margin-bottom: 1.5rem;">Scanned: Chicken breast, brown rice, broccoli</p>
                        
                        <div style="background-color: rgba(255,255,255,0.02); padding: 1rem; border-radius: 12px; border: 1px solid var(--border); margin-bottom: 1rem;">
                            <div style="display:flex; justify-content:space-between; font-size: 0.85rem; font-weight:700;">
                                <span>Protein Target Achieved</span>
                                <span style="color: var(--primary);">88%</span>
                            </div>
                            <div class="visual-item-bar"><div class="visual-item-fill" style="width: 88%;" id="fill-0"></div></div>
                        </div>
                        
                        <div style="background-color: rgba(255,255,255,0.02); padding: 1rem; border-radius: 12px; border: 1px solid var(--border);">
                            <div style="display:flex; justify-content:space-between; font-size: 0.85rem; font-weight:700;">
                                <span>Healthy Fats Level</span>
                                <span style="color: #00ffcc;">60%</span>
                            </div>
                            <div class="visual-item-bar"><div class="visual-item-fill" style="width: 60%; background: #00ffcc;" id="fill-1"></div></div>
                        </div>
                    </div>

                    <!-- Tab 2 Visual -->
                    <div class="visual-display" id="tab-visual-1">
                        <h4 style="margin-bottom: 0.5rem; color: var(--primary);">Form Coach Evaluation</h4>
                        <p style="font-size: 0.9rem; color: var(--muted); margin-bottom: 1.5rem;">Current Exercise: Barbell Squat</p>
                        
                        <div style="border-left: 3px solid var(--primary); padding-left: 1rem; margin-bottom: 1rem;">
                            <h5 style="font-size: 0.9rem;">Depth Metric: <span style="color: var(--primary);">Excellent</span></h5>
                            <p style="font-size: 0.8rem; color: var(--muted); margin-top: 0.2rem;">Hip crease went below knee level cleanly.</p>
                        </div>
                        <div style="border-left: 3px solid #ffcc00; padding-left: 1rem;">
                            <h5 style="font-size: 0.9rem;">Bar Path: <span style="color: #ffcc00;">Slight Forward Tilt</span></h5>
                            <p style="font-size: 0.8rem; color: var(--muted); margin-top: 0.2rem;">Keep chest raised slightly more during ascent.</p>
                        </div>
                    </div>

                    <!-- Tab 3 Visual -->
                    <div class="visual-display" id="tab-visual-2">
                        <h4 style="margin-bottom: 1rem; color: var(--primary);">Weekly Consistency Metric</h4>
                        <p style="font-size: 0.9rem; color: var(--muted); margin-bottom: 1.5rem;">Total Workouts: 5 Sessions Logged</p>
                        
                        <div style="display: flex; gap: 0.5rem; justify-content: space-between; align-items: flex-end; height: 120px; padding: 1rem 0;">
                            <div style="display:flex; flex-direction:column; align-items:center; flex:1;">
                                <div style="height: 40px; background: rgba(255,255,255,0.1); width: 100%; border-radius: 4px;"></div>
                                <span style="font-size: 0.7rem; color: var(--muted); margin-top: 0.5rem;">Mon</span>
                            </div>
                            <div style="display:flex; flex-direction:column; align-items:center; flex:1;">
                                <div style="height: 80px; background: var(--primary); width: 100%; border-radius: 4px; box-shadow: 0 0 10px var(--primary-glow);"></div>
                                <span style="font-size: 0.7rem; color: var(--muted); margin-top: 0.5rem;">Tue</span>
                            </div>
                            <div style="display:flex; flex-direction:column; align-items:center; flex:1;">
                                <div style="height: 60px; background: var(--primary); width: 100%; border-radius: 4px; box-shadow: 0 0 10px var(--primary-glow);"></div>
                                <span style="font-size: 0.7rem; color: var(--muted); margin-top: 0.5rem;">Wed</span>
                            </div>
                            <div style="display:flex; flex-direction:column; align-items:center; flex:1;">
                                <div style="height: 95px; background: var(--primary); width: 100%; border-radius: 4px; box-shadow: 0 0 10px var(--primary-glow);"></div>
                                <span style="font-size: 0.7rem; color: var(--muted); margin-top: 0.5rem;">Thu</span>
                            </div>
                            <div style="display:flex; flex-direction:column; align-items:center; flex:1;">
                                <div style="height: 30px; background: rgba(255,255,255,0.1); width: 100%; border-radius: 4px;"></div>
                                <span style="font-size: 0.7rem; color: var(--muted); margin-top: 0.5rem;">Fri</span>
                            </div>
                            <div style="display:flex; flex-direction:column; align-items:center; flex:1;">
                                <div style="height: 110px; background: var(--primary); width: 100%; border-radius: 4px; box-shadow: 0 0 10px var(--primary-glow);"></div>
                                <span style="font-size: 0.7rem; color: var(--muted); margin-top: 0.5rem;">Sat</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Download Section -->
        <section class="download-section" id="download">
            <div class="download-card">
                <h2>Get Fitrova Today</h2>
                <p>Download our secure APK to load the application on your Android device and gain immediate access to advanced automated training models.</p>
                
                <div class="download-actions">
                    <a href="/Fitrova.apk" class="btn" id="apkDownloadLink" download="Fitrova.apk">Download Android APK</a>
                    <a href="/privacy.php" class="btn btn-outline">Read Privacy Policy</a>
                </div>

                <div class="download-meta">
                    <div class="download-meta-item">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" style="color:var(--primary);flex-shrink:0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Clean APK Build
                    </div>
                    <div class="download-meta-item">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" style="color:var(--primary);flex-shrink:0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Size: ~111 MB
                    </div>
                    <div class="download-meta-item">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" style="color:var(--primary);flex-shrink:0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Android 9.0+ Supported
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-grid">
                <div class="footer-brand">
                    <h3>Fitrova<span>.</span></h3>
                    <p>Next-generation fitness tracking and dynamic coaching. Powering athletic longevity through state-of-the-art artificial intelligence models.</p>
                </div>
                <div class="footer-col">
                    <h4>Product</h4>
                    <ul>
                        <li><a href="#features">Features</a></li>
                        <li><a href="#showcase">Inside the App</a></li>
                        <li><a href="#download">Download</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Legal</h4>
                    <ul>
                        <li><a href="/privacy.php">Privacy Policy</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Contact Us</h4>
                    <ul>
                        <li>Email: <a href="mailto:ibehpromise30@gmail.com">ibehpromise30@gmail.com</a></li>
                        <li>Support: <a href="mailto:ibehpromise30@gmail.com">Contact Support</a></li>
                    </ul>
                </div>
            </div>

            <div class="footer-bottom">
                <p>&copy; 2026 Fitrova. All rights reserved.</p>
                <p>Designed for athletes everywhere.</p>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script>
        function switchTab(index) {
            // Remove active classes
            const tabs = document.querySelectorAll('.showcase-tab');
            tabs.forEach(tab => tab.classList.remove('active'));

            const visuals = document.querySelectorAll('.visual-display');
            visuals.forEach(visual => visual.classList.remove('active'));

            // Add active classes
            tabs[index].classList.add('active');
            
            const selectedVisual = document.getElementById('tab-visual-' + index);
            selectedVisual.classList.add('active');
        }

        // Auto transition progress bars inside tab visual 1
        window.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                const fill0 = document.getElementById('fill-0');
                if (fill0) fill0.style.width = '88%';
                const fill1 = document.getElementById('fill-1');
                if (fill1) fill1.style.width = '60%';
            }, 500);
        });
    </script>
</body>
</html>
