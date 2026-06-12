<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful | Fitrova</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
            background-color: #F9FAFB;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: #111827;
            padding: 24px;
        }
        .container {
            width: 100%;
            max-width: 400px;
            background: #FFFFFF;
            border-radius: 28px;
            padding: 40px 32px;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            border: 1px solid #E5E7EB;
        }
        .icon-circle {
            width: 80px;
            height: 80px;
            border-radius: 40px;
            background-color: #D1FAE5;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto 24px;
            position: relative;
        }
        .checkmark {
            color: #10B981;
            font-size: 40px;
            font-weight: bold;
        }
        h1 {
            font-size: 22px;
            font-weight: 800;
            margin-bottom: 12px;
            color: #111827;
            letter-spacing: -0.5px;
        }
        p {
            font-size: 14px;
            color: #6B7280;
            line-height: 20px;
            margin-bottom: 32px;
        }
        .spinner {
            width: 24px;
            height: 24px;
            border: 3px solid #E5E7EB;
            border-top: 3px solid #10B981;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .reference-badge {
            background-color: #F3F4F6;
            padding: 6px 12px;
            border-radius: 100px;
            font-size: 11px;
            color: #4B5563;
            font-weight: 600;
            display: inline-block;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon-circle">
            <span class="checkmark">✓</span>
        </div>
        <h1>Payment Verified!</h1>
        <p>Congratulations, your transaction completed successfully. Securely synchronizing with the Fitrova app now...</p>
        
        <div class="spinner"></div>

        <?php if (isset($_GET['reference'])): ?>
            <div class="reference-badge">
                Ref: <?php echo htmlspecialchars($_GET['reference']); ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
