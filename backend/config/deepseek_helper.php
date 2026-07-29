<?php
/**
 * deepseek_helper.php
 *
 * Shared helper: calls DeepSeek API (https://api.deepseek.com/chat/completions)
 * using standard OpenAI-compatible JSON payload.
 *
 * Usage:
 *   require_once __DIR__ . '/../../../config/deepseek_helper.php';
 *   $result = callDeepSeek($prompt, $apiKey, $systemPrompt);
 */

function callDeepSeek(
    string $userPrompt,
    string $apiKey,
    string $systemPrompt = '',
    float $temperature = 0.7,
    string $model = 'deepseek-chat',
    int $maxTokens = 2000
): string {
    if (empty($apiKey)) {
        throw new Exception('DeepSeek API key is missing.');
    }

    $messages = [];
    if (!empty($systemPrompt)) {
        $messages[] = ['role' => 'system', 'content' => $systemPrompt];
    }
    $messages[] = ['role' => 'user', 'content' => $userPrompt];

    $payload = [
        'model'           => $model,
        'messages'        => $messages,
        'temperature'     => $temperature,
        'max_tokens'      => $maxTokens,
        'response_format' => ['type' => 'json_object']
    ];

    $ch = curl_init('https://api.deepseek.com/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST,           true);
    curl_setopt($ch, CURLOPT_POSTFIELDS,     json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        "Authorization: Bearer {$apiKey}"
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT,        60);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    $raw     = curl_exec($ch);
    $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($raw === false || $code !== 200) {
        throw new Exception("DeepSeek API error — HTTP {$code}: {$curlErr} | {$raw}");
    }

    $resp    = json_decode($raw, true);
    $content = $resp['choices'][0]['message']['content'] ?? '';

    if (empty($content)) {
        throw new Exception("DeepSeek returned empty content.");
    }

    // Strip markdown code fences if present
    $content = preg_replace('/^```json\s*/i', '', trim($content));
    $content = preg_replace('/^```\s*/i',     '', trim($content));
    $content = preg_replace('/```\s*$/i',     '', trim($content));

    return trim($content);
}
?>
