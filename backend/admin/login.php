<?php
// backend/admin/login.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/../config/db_config.php';

// Self-Heal Database Schema (Ensure tables and columns exist)
try {
    // If users table, payment_transactions table, user_challenges table, or user_connections table is missing, initialize the database tables and seed defaults
    $usersTable = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
    $paymentTable = $pdo->query("SHOW TABLES LIKE 'payment_transactions'")->fetch();
    $challengesTable = $pdo->query("SHOW TABLES LIKE 'user_challenges'")->fetch();
    $connectionsTable = $pdo->query("SHOW TABLES LIKE 'user_connections'")->fetch();
    if (!$usersTable || !$paymentTable || !$challengesTable || !$connectionsTable) {
        ob_start();
        require_once __DIR__ . '/../scripts/setup_production_db.php';
        ob_end_clean();
    }

    // Ensure the is_admin column exists
    $checkCol = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_admin'")->fetch();
    if (!$checkCol) {
        $pdo->exec("ALTER TABLE users ADD COLUMN is_admin TINYINT(1) DEFAULT 0");
        $pdo->exec("UPDATE users SET is_admin = 1 WHERE email = 'admin@fitrova.com'");
    }
} catch (PDOException $e) {
    error_log("Self-healing failed in login.php: " . $e->getMessage());
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both your email and password.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, password_hash, is_admin FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                if (isset($user['is_admin']) && (int) $user['is_admin'] === 1) {
                    // Authenticate and set session keys
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_email'] = $user['email'];
                    $_SESSION['admin_name'] = $user['first_name'] . ' ' . $user['last_name'];

                    header("Location: index.php");
                    exit();
                } else {
                    $error = 'Access denied. Administrator credentials required.';
                }
            } else {
                $error = 'Invalid email address or password.';
            }
        } catch (Exception $e) {
            $error = 'A database connection error occurred: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <link rel="icon" type="image/png" href="favicon.png" />
    <title>Fitrova Admin - Nexus Gateway</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap"
        rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet" />
    <style>
        :root {
            --primary: #13ec13;
            --primary-dim: #06bf06;
            --bg-dark: #070a12;
            --surface-glass: rgba(255, 255, 255, 0.03);
            --border-glass: rgba(255, 255, 255, 0.08);
            --text-main: #f8faf8;
            --text-muted: #94a3b8;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: var(--bg-dark);
            color: var(--text-main);
            font-family: 'Manrope', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        /* Glowing background spots */
        .glow-spot {
            position: absolute;
            border-radius: 50%;
            filter: blur(140px);
            z-index: 1;
            pointer-events: none;
            opacity: 0.65;
        }

        .glow-green {
            bottom: -10%;
            left: -10%;
            width: 50vw;
            height: 50vw;
            background: radial-gradient(circle, rgba(19, 236, 19, 0.15) 0%, rgba(19, 236, 19, 0) 70%);
        }

        .glow-orange {
            top: -10%;
            right: -10%;
            width: 45vw;
            height: 45vw;
            background: radial-gradient(circle, rgba(251, 146, 60, 0.08) 0%, rgba(251, 146, 60, 0) 70%);
        }

        /* Card Container */
        .login-card {
            width: 100%;
            max-width: 440px;
            padding: 2.5rem;
            background: var(--surface-glass);
            border: 1px solid var(--border-glass);
            border-radius: 2rem;
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
            z-index: 10;
            animation: cardFadeIn 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            position: relative;
        }

        @keyframes cardFadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Branding */
        .branding {
            text-align: center;
            margin-bottom: 2rem;
        }

        .branding h1 {
            font-size: 2.25rem;
            font-weight: 800;
            letter-spacing: -0.05em;
            color: var(--text-main);
            margin-bottom: 0.25rem;
        }

        .branding h1 span {
            color: var(--primary);
        }

        .branding p {
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            color: var(--text-muted);
        }

        /* Form Controls */
        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-label {
            display: block;
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            color: var(--text-muted);
            font-size: 1.25rem;
            user-select: none;
            transition: color 0.3s ease;
        }

        .form-input {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 1rem;
            color: var(--text-main);
            font-family: inherit;
            font-size: 0.9rem;
            outline: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .form-input:hover {
            border-color: rgba(19, 236, 19, 0.3);
            background: rgba(255, 255, 255, 0.06);
        }

        .form-input:focus {
            border-color: var(--primary);
            background: rgba(255, 255, 255, 0.08);
            box-shadow: 0 0 12px rgba(19, 236, 19, 0.15);
        }

        .form-input:focus+.input-icon {
            color: var(--primary);
        }

        /* Submit Button */
        .btn-submit {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dim) 100%);
            border: none;
            border-radius: 1rem;
            color: #0c140c;
            font-family: inherit;
            font-size: 0.85rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 8px 20px -4px rgba(19, 236, 19, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px -2px rgba(19, 236, 19, 0.45);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        /* Error Banner */
        .error-banner {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            border-radius: 1rem;
            padding: 0.85rem 1rem;
            color: #f87171;
            font-size: 0.8rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            animation: shake 0.4s ease-in-out;
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            25% {
                transform: translateX(-6px);
            }

            75% {
                transform: translateX(6px);
            }
        }

        .error-banner span {
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        /* Subtle copyright footer */
        .footer-text {
            text-align: center;
            margin-top: 2rem;
            font-size: 0.7rem;
            color: var(--text-muted);
            letter-spacing: 0.05em;
        }
    </style>
</head>

<body>
    <!-- Background Blur Shapes -->
    <div class="glow-spot glow-green"></div>
    <div class="glow-spot glow-orange"></div>

    <!-- Main Container -->
    <div class="login-card">
        <!-- Branding Logo Header -->
        <div class="branding" style="display: flex; flex-direction: column; align-items: center; justify-content: center;">
            <div style="display: flex; align-items: center; gap: 0.6rem; justify-content: center;">
                <img src="/Logo.png" alt="Fitrova Logo" style="height: 38px; width: auto;">
                <h1 style="margin: 0; font-size: 2.25rem; font-weight: 800; letter-spacing: -0.05em; color: var(--text-main);">Fitrova<span style="color: var(--primary);">.</span></h1>
            </div>
            <p style="margin-top: 0.5rem; color: var(--text-muted); font-size: 0.95rem; font-weight: 500;">Admin Gateway</p>
        </div>

        <!-- Feedback Messages -->
        <?php if (!empty($error)): ?>
            <div class="error-banner">
                <span class="material-symbols-outlined">warning</span>
                <div><?php echo htmlspecialchars($error); ?></div>
            </div>
        <?php endif; ?>

        <!-- Form Elements -->
        <form action="login.php" method="POST">
            <!-- Email -->
            <div class="form-group">
                <label class="form-label" for="email">Security Identifier (Email)</label>
                <div class="input-wrapper">
                    <input class="form-input" id="email" name="email" placeholder="admin@fitrova.com" required
                        type="email" autocomplete="username" />
                    <span class="material-symbols-outlined input-icon">alternate_email</span>
                </div>
            </div>

            <!-- Password -->
            <div class="form-group">
                <label class="form-label" for="password">Access Token (Password)</label>
                <div class="input-wrapper">
                    <input class="form-input" id="password" name="password" placeholder="••••••••" required
                        type="password" autocomplete="current-password" />
                    <span class="material-symbols-outlined input-icon">lock</span>
                </div>
            </div>

            <!-- Submit -->
            <button class="btn-submit" type="submit">
                <span>Decrypt &amp; Access</span>
                <span class="material-symbols-outlined" style="font-size: 18px;">vpn_key</span>
            </button>
        </form>

        <p class="footer-text">SECURE PORTAL &bull; FITROVA NEXUS SYSTEMS &copy; 2026</p>
    </div>
</body>

</html>