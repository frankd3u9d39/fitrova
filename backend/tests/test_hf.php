<?php
$url = 'https://ibeh12-fitrova-ai.hf.space/api/analyze-youtube';

echo "Connecting to: $url\n";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
if ($curlErr) {
    echo "cURL Error: $curlErr\n";
} else {
    echo "Response (first 200 chars):\n";
    echo substr($response, 0, 200) . "\n";
}
