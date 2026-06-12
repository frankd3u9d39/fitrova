<?php
// scratch/test_google_auth.php
$url = "http://localhost/Fitrova/backend/app/controllers/auth/google_auth.php";

echo "=== SCENARIO 1: Request with Missing ID Token ===\n";

$payload1 = [
    "someRandomData" => "not_a_token"
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload1));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response1 = curl_exec($ch);
$httpCode1 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode1 (Expected: 400)\n";
echo "Response JSON:\n" . json_encode(json_decode($response1), JSON_PRETTY_PRINT) . "\n\n";

echo "=== SCENARIO 2: Request with Invalid/Expired Google ID Token ===\n";

$payload2 = [
    "idToken" => "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older-12345"
];

$ch2 = curl_init($url);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_POST, true);
curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode($payload2));
curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch2, CURLOPT_TIMEOUT, 10);

$response2 = curl_exec($ch2);
$httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

echo "HTTP Code: $httpCode2 (Expected: 401)\n";
echo "Response JSON:\n" . json_encode(json_decode($response2), JSON_PRETTY_PRINT) . "\n\n";

echo "=== SCENARIO 3: Request with Valid Mock ID Token (Alex Mercer) ===\n";

$payload3 = [
    "idToken" => "dev-mock-google-token-alex"
];

$ch3 = curl_init($url);
curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch3, CURLOPT_POST, true);
curl_setopt($ch3, CURLOPT_POSTFIELDS, json_encode($payload3));
curl_setopt($ch3, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch3, CURLOPT_TIMEOUT, 10);

$response3 = curl_exec($ch3);
$httpCode3 = curl_getinfo($ch3, CURLINFO_HTTP_CODE);
curl_close($ch3);

echo "HTTP Code: $httpCode3 (Expected: 200 or 201)\n";
echo "Response JSON:\n" . json_encode(json_decode($response3), JSON_PRETTY_PRINT) . "\n\n";

echo "=== SCENARIO 4: Request with Custom Mock ID Token (Johnny Appleseed) ===\n";

$payload4 = [
    "idToken" => "dev-mock-google-token-custom:johnny.appleseed@gmail.com:Johnny:Appleseed"
];

$ch4 = curl_init($url);
curl_setopt($ch4, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch4, CURLOPT_POST, true);
curl_setopt($ch4, CURLOPT_POSTFIELDS, json_encode($payload4));
curl_setopt($ch4, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch4, CURLOPT_TIMEOUT, 10);

$response4 = curl_exec($ch4);
$httpCode4 = curl_getinfo($ch4, CURLINFO_HTTP_CODE);
curl_close($ch4);

echo "HTTP Code: $httpCode4 (Expected: 200 or 201)\n";
echo "Response JSON:\n" . json_encode(json_decode($response4), JSON_PRETTY_PRINT) . "\n\n";

echo "=== INTEGRATION TEST SUMMARY ===\n";
if ($httpCode1 === 400 && $httpCode2 === 401 && ($httpCode3 === 200 || $httpCode3 === 201) && ($httpCode4 === 200 || $httpCode4 === 201)) {
    echo "SUCCESS: Security validation rules & development mock authentication bypass are active and functioning correctly on the backend.\n";
} else {
    echo "FAILURE: Check backend google_auth.php handler error flows.\n";
}
?>
