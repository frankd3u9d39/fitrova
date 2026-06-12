<?php
$GEMINI_API_KEY = 'AQ.Ab8RN6K04-jc_xK7I1yOSz291VKJ1pwm0j5izQMReuOhalV7uA';
$url = "https://generativelanguage.googleapis.com/v1beta/models?key={$GEMINI_API_KEY}";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$data = json_decode($response, true);

if (isset($data['models'])) {
    foreach ($data['models'] as $m) {
        echo $m['name'] . " (" . $m['displayName'] . ")\n";
    }
} else {
    echo "No models found: " . $response . "\n";
}
