<?php
// backend/app/middleware/AISubscriptionGate.php
namespace App\Middleware;

use PDO;
use Exception;

class AISubscriptionGate {
    /**
     * Verifies if a user has active access to premium AI features.
     * For workouts: allows exactly ONE free trial session ever.
     * For other features: requires active subscription immediately.
     * 
     * Returns an array with access details, or terminates execution with a JSON 403 response.
     */
    public static function verifyAccess($pdo, $userId, $requiredTier = 'premium', $featureName = "Premium AI Feature", $trialColumn = 'trial_used') {
        if (!$userId) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'User ID is required for access verification']);
            exit();
        }

        try {
            // ── Master Paywall Switch ──────────────────────────────────────────
            // If monetization_enabled is false (off by default), bypass ALL paywall
            // checks and grant free access to everyone.
            $paywallRow = $pdo->query(
                "SELECT setting_value FROM system_settings WHERE setting_key = 'monetization_enabled' LIMIT 1"
            )->fetch(PDO::FETCH_ASSOC);
            $paywallOn = ($paywallRow && $paywallRow['setting_value'] === 'true');

            if (!$paywallOn) {
                return [
                    'has_access' => true,
                    'is_trial'   => false,
                    'tier'       => 'free',
                    'paywall_off' => true,
                ];
            }

            // Fetch user subscription info (dynamically include extra trial column if needed)
            $extraCol = ($trialColumn !== 'trial_used') ? ", $trialColumn" : '';
            $stmt = $pdo->prepare("
                SELECT subscription_tier, trial_used, subscription_expiry $extraCol
                FROM user_profiles 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$profile) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'User profile not found']);
                exit();
            }

            $tier = strtolower($profile['subscription_tier'] ?? 'free');
            $trialUsed = intval($profile[$trialColumn] ?? 0);
            $expiry = $profile['subscription_expiry'] ?? null;

            // Determine if subscription is active (not expired)
            $isSubscribed = false;
            if ($tier === 'premium' || $tier === 'advanced_premium') {
                if ($expiry === null || strtotime($expiry) > time()) {
                    $isSubscribed = true;
                }
            }

            // 1. Authorized Active Subscriber
            if ($isSubscribed) {
                if ($requiredTier === 'advanced_premium' && $tier !== 'advanced_premium') {
                    self::renderLockResponse(
                        "🚀 Unlock Advanced AI Premium",
                        "Upgrade to Fitrova Advanced Premium to unlock the AI Nutrition Coach, camera meal scanners, and camera biomechanics posture checking!",
                        "UPGRADE_ADVANCED"
                    );
                }
                return [
                    'has_access' => true,
                    'is_trial' => false,
                    'tier' => $tier
                ];
            }

            // 2. Free User — allow one-time trial if trial slot not yet consumed
            if ($trialUsed === 0) {
                return [
                    'has_access' => true,
                    'is_trial' => true,
                    'tier' => 'free',
                    'trial_column' => $trialColumn
                ];
            }

            // 3. Locked Access — trial already consumed or feature requires subscription
            $lockTitle = "✨ Unlock " . $featureName;
            $lockMsg = "Trial used. Upgrade to Premium or Advanced Premium to unlock unlimited access!";
 
            self::renderLockResponse($lockTitle, $lockMsg, "UPGRADE_PROMPT");

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Subscription validation error: ' . $e->getMessage()]);
            exit();
        }
    }

    /**
     * Marks the one-time trial as consumed for a given trial column.
     * Default column is 'trial_used'; pass 'form_trial_used' for form checker, etc.
     */
    public static function consumeTrial($pdo, $userId, $trialColumn = 'trial_used') {
        try {
            // Whitelist allowed column names to prevent SQL injection
            $allowed = ['trial_used', 'form_trial_used', 'scan_trial_used', 'diet_trial_used'];
            if (!in_array($trialColumn, $allowed)) {
                $trialColumn = 'trial_used';
            }
            $stmt = $pdo->prepare("UPDATE user_profiles SET $trialColumn = 1 WHERE user_id = ?");
            $stmt->execute([$userId]);
        } catch (Exception $e) {
            error_log("Failed to mark trial ($trialColumn) as used for user $userId: " . $e->getMessage());
        }
    }

    /**
     * Outputs a standardized JSON locked subscription response and exits.
     */
    private static function renderLockResponse($title, $message, $action = "UPGRADE_PROMPT") {
        http_response_code(403);
        echo json_encode([
            'status' => 'subscription_locked',
            'title' => $title,
            'message' => $message,
            'action' => $action,
            'pricing_options' => [
                'premium' => '₦1,500/month',
                'advanced' => '₦3,000/month (Save 20% on visual AI suite)'
            ]
        ]);
        exit();
    }
}
?>
