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

        .hero-badge span {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: var(--primary);
            display: inline-block;
            box-shadow: 0 0 8px var(--primary);
        }

        /* Mockup Mobile UI */
        .hero-mockup-wrapper {
            position: relative;
            animation: float 6s ease-in-out infinite;
        }

        .phone-mockup {
            width: 100%;
            max-width: 380px;
            background: var(--surface);
            border: 6px solid #1a2333;
            border-radius: 40px;
            box-shadow: var(--card-shadow), 0 0 40px rgba(19, 236, 19, 0.05);
            padding: 1.5rem;
            margin: 0 auto;
            position: relative;
            overflow: hidden;
        }

        .phone-mockup::before {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 140px;
            height: 25px;
            background-color: #1a2333;
            border-bottom-left-radius: 18px;
            border-bottom-right-radius: 18px;
            z-index: 10;
        }

        .phone-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 0.8rem;
            margin-bottom: 1.5rem;
        }

        .phone-user {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .phone-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, #00ffcc 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            color: #000;
            font-size: 0.9rem;
            border: 2px solid rgba(255, 255, 255, 0.1);
        }

        .phone-user-info h4 {
            font-size: 0.9rem;
            font-weight: 700;
        }

        .phone-user-info p {
            font-size: 0.75rem;
            color: var(--muted);
        }

        .phone-status-badge {
            background-color: rgba(19, 236, 19, 0.1);
            border: 1px solid rgba(19, 236, 19, 0.2);
            color: var(--primary);
            font-size: 0.7rem;
            font-weight: 700;
            padding: 0.2rem 0.6rem;
            border-radius: 99px;
        }

        /* SVG Circle Progress */
        .progress-card {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 1.25rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .progress-circle-wrapper {
            position: relative;
            width: 80px;
            height: 80px;
        }

        .progress-circle-bg {
            fill: none;
            stroke: rgba(255, 255, 255, 0.04);
            stroke-width: 8;
        }

        .progress-circle-val {
            fill: none;
            stroke: var(--primary);
            stroke-width: 8;
            stroke-dasharray: 226;
            stroke-dashoffset: 61; /* 73% */
            stroke-linecap: round;
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
            transition: stroke-dashoffset 1s ease;
        }

        .progress-percentage {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-weight: 800;
            font-size: 1.1rem;
        }

        .progress-details h5 {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--muted);
            margin-bottom: 0.25rem;
        }

        .progress-details p {
            font-size: 1.1rem;
            font-weight: 800;
        }

        .progress-details span {
            font-size: 0.8rem;
            color: var(--muted);
            font-weight: 400;
        }

        /* Nutrition Bars */
        .macro-card {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 1.25rem;
            margin-bottom: 1.25rem;
        }

        .macro-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
        }

        .macro-header h5 {
            font-size: 0.85rem;
            font-weight: 700;
        }

        .macro-bar-container {
            display: flex;
            flex-direction: column;
            gap: 0.6rem;
        }

        .macro-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.75rem;
        }

        .macro-label {
            width: 60px;
            color: var(--muted);
        }

        .macro-bar-bg {
            flex-grow: 1;
            height: 6px;
            background-color: rgba(255, 255, 255, 0.05);
            border-radius: 3px;
            margin: 0 0.75rem;
            overflow: hidden;
        }

        .macro-bar-fill {
            height: 100%;
            border-radius: 3px;
            background-color: var(--primary);
        }

        .macro-val {
            font-weight: 700;
            width: 65px;
            text-align: right;
        }

        /* AI Coach Tip Card */
        .coach-card {
            background: linear-gradient(135deg, rgba(19, 236, 19, 0.1) 0%, rgba(0, 0, 0, 0) 100%);
            border: 1px solid rgba(19, 236, 19, 0.2);
            border-radius: 20px;
            padding: 1.25rem;
        }

        .coach-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .coach-header h5 {
            font-size: 0.85rem;
            font-weight: 800;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .coach-card p {
            font-size: 0.8rem;
            color: #cbd5e1;
            line-height: 1.5;
        }

        /* floating badge */
        .floating-feature-badge {
            position: absolute;
            background: rgba(13, 19, 33, 0.85);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border);
            padding: 0.75rem 1rem;
            border-radius: 16px;
            font-size: 0.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        }

        .badge-1 {
            top: 20%;
            left: -40px;
            border-color: rgba(19, 236, 19, 0.3);
        }

        .badge-2 {
            bottom: 15%;
            right: -30px;
            border-color: rgba(0, 255, 204, 0.3);
        }

        .floating-feature-badge i {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
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

        .download-meta-item i {
            color: var(--primary);
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
                    <a href="#download" class="btn" id="heroDownloadBtn">Download APK</a>
                    <a href="#features" class="btn btn-outline">Explore Features</a>
                </div>

                <div class="hero-badges">
                    <div class="hero-badge"><span></span> AI Personal Coach</div>
                    <div class="hero-badge"><span></span> Smart Nutrition Scan</div>
                    <div class="hero-badge"><span></span> Live Form Analysis</div>
                </div>
            </div>

            <!-- Dashboard Mockup -->
            <div class="hero-mockup-wrapper">
                <div class="phone-mockup">
                    <div class="phone-header">
                        <div class="phone-user">
                            <div class="phone-avatar">AR</div>
                            <div class="phone-user-info">
                                <h4>Alex Rivera</h4>
                                <p>Fitrova Athlete</p>
                            </div>
                        </div>
                        <div class="phone-status-badge">AI Active</div>
                    </div>

                    <!-- Progress Ring -->
                    <div class="progress-card">
                        <div class="progress-details">
                            <h5>Calorie Budget</h5>
                            <p>1,460 <span>/ 2,000 kcal</span></p>
                        </div>
                        <div class="progress-circle-wrapper">
                            <svg width="80" height="80">
                                <circle class="progress-circle-bg" cx="40" cy="40" r="36" />
                                <circle class="progress-circle-val" cx="40" cy="40" r="36" id="progressVal" />
                            </svg>
                            <div class="progress-percentage">73%</div>
                        </div>
                    </div>

                    <!-- Macros Bar -->
                    <div class="macro-card">
                        <div class="macro-header">
                            <h5>Macro Breakdown</h5>
                            <span style="font-size: 0.75rem; color: var(--primary); font-weight: 700;">Optimal</span>
                        </div>
                        <div class="macro-bar-container">
                            <!-- Protein -->
                            <div class="macro-item">
                                <span class="macro-label">Protein</span>
                                <div class="macro-bar-bg">
                                    <div class="macro-bar-fill" style="width: 80%; background-color: #13ec13;"></div>
                                </div>
                                <span class="macro-val">120g / 150g</span>
                            </div>
                            <!-- Carbs -->
                            <div class="macro-item">
                                <span class="macro-label">Carbs</span>
                                <div class="macro-bar-bg">
                                    <div class="macro-bar-fill" style="width: 72%; background-color: #00ffcc;"></div>
                                </div>
                                <span class="macro-val">180g / 250g</span>
                            </div>
                            <!-- Fats -->
                            <div class="macro-item">
                                <span class="macro-label">Fats</span>
                                <div class="macro-bar-bg">
                                    <div class="macro-bar-fill" style="width: 55%; background-color: #ffcc00;"></div>
                                </div>
                                <span class="macro-val">44g / 80g</span>
                            </div>
                        </div>
                    </div>

                    <!-- AI Coach Suggestion -->
                    <div class="coach-card">
                        <div class="coach-header">
                            <span style="font-size: 0.8rem;">⚡</span>
                            <h5>AI Coach Advice</h5>
                        </div>
                        <p>"Hey Alex, you need 30g more protein today. Grab a Greek yogurt snack before your upcoming squat session!"</p>
                    </div>
                </div>
                
                <!-- Floating tags -->
                <div class="floating-feature-badge badge-1"><i></i> Meal Scan Active</div>
                <div class="floating-feature-badge badge-2"><i></i> Squat Tracker 98%</div>
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
                    <div class="feature-icon-wrapper">🏋️</div>
                    <h3>AI Workout Generation</h3>
                    <p>Get dynamic workout routines generated on-demand by state-of-the-art language models. Tailored to your fitness levels, equipment availability, and target muscle groups.</p>
                </div>

                <!-- Form analysis -->
                <div class="feature-card">
                    <div class="feature-icon-wrapper">👁️</div>
                    <h3>Pose & Form Tracking</h3>
                    <p>Protect your joints and build solid posture. Fitrova's computer vision engine evaluates critical angles of exercises like squats, providing immediate visual hints.</p>
                </div>

                <!-- Nutrition -->
                <div class="feature-card">
                    <div class="feature-icon-wrapper">🥗</div>
                    <h3>Smart Nutrition Analyzer</h3>
                    <p>Effortlessly log your food entries and instantly receive complete macronutrient and micronutrient profiles, allowing you to fine-tune your caloric goals.</p>
                </div>

                <!-- Community -->
                <div class="feature-card">
                    <div class="feature-icon-wrapper">👥</div>
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
                    <a href="https://expo.dev/accounts/holand455/projects/fitrova/builds/b1ac55bf-2bd2-42ca-bb10-831be7fdf0f8" class="btn" id="apkDownloadLink" target="_blank">Download Android APK</a>
                    <a href="/privacy.php" class="btn btn-outline">Read Privacy Policy</a>
                </div>

                <div class="download-meta">
                    <div class="download-meta-item"><i>✓</i> Clean APK Build</div>
                    <div class="download-meta-item"><i>✓</i> Size: ~45 MB</div>
                    <div class="download-meta-item"><i>✓</i> Android 9.0+ Supported</div>
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
