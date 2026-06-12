<?php
// scratch/test_outgoing_curl.php

$url = "https://oauth2.googleapis.com/tokeninfo?id_token=test";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

$response = curl_exec($ch);
$errNo = curl_errno($ch);
$errMsg = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "cURL Error No: $errNo\n";
echo "cURL Error Msg: $errMsg\n";
echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";
?>
