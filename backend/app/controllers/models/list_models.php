<?php
$apiKey = 'AQ.Ab8RN6JCMfsoU7_dHDI7TQsk_23gXP2PUnL2Vjj-oPsZuO2y2A';
$url = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . $apiKey;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

echo $response;
?>
