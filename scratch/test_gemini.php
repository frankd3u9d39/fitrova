<?php
$url = "http://localhost/Fitrova/backend/app/controllers/nutrition/ai_food_recommendations.php";

$payload = [
    "user_id" => 6
];

echo "📡 Simulating React Native app POST call to $url for User 6...\n\n";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "📡 Response HTTP Code: " . $httpCode . "\n";
if ($curlError) {
    echo "❌ cURL Error: " . $curlError . "\n";
} else {
    echo "📝 Controller Response Body:\n" . $response . "\n";
}
?>
