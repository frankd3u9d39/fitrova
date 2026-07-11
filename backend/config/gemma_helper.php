<?php
/**
 * gemma_helper.php
 *
 * Shared helper: calls google/gemma-3-4b-it via the Hugging Face Inference
 * Providers API (GPU-backed, fast).
 *
 * Usage:
 *   require_once __DIR__ . '/../../../config/gemma_helper.php';
 *   $result = callGemma3($prompt, getenv('HF_TOKEN'));
 *
 * Returns: the raw string content from the model, or throws on failure.
 */

function callGemma3(string $userPrompt, string $hfToken, int $maxTokens = 600): string {
    if (empty($hfToken)) {
        throw new Exception('HF_TOKEN not set — Gemma 3 unavailable.');
    }

    $payload = json_encode([
        'model'    => 'google/gemma-3-4b-it',
        'messages' => [['role' => 'user', 'content' => $userPrompt]],
        'max_tokens'  => $maxTokens,
        'temperature' => 0.7,
    ]);

    $ch = curl_init('https://router.huggingface.co/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST,           true);
    curl_setopt($ch, CURLOPT_POSTFIELDS,     $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        "Authorization: Bearer {$hfToken}",
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT,        30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    $raw     = curl_exec($ch);
    $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($raw === false || $code !== 200) {
        throw new Exception("Gemma 3 API error — HTTP {$code}: {$curlErr} | {$raw}");
    }

    $resp = json_decode($raw, true);
    $text = $resp['choices'][0]['message']['content'] ?? '';

    if (empty($text)) {
        throw new Exception("Gemma 3 returned empty content.");
    }

    // Strip markdown code fences that Gemma sometimes wraps around JSON
    $text = preg_replace('/^```json\s*/i', '', trim($text));
    $text = preg_replace('/```\s*$/i',     '', trim($text));
    return trim($text);
}
?>
