<?php
$ids = ['aclHkVaku9U', 'gC_L9qAHML8'];

foreach ($ids as $id) {
    echo "=== Debugging $id ===\n";
    $url = "https://www.youtube.com/watch?v=" . $id;
    $oembed = "https://www.youtube.com/oembed?url=" . urlencode($url) . "&format=json";
    
    $ch = curl_init($oembed);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true); // Return headers as well
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    
    echo "HTTP Code: $code\n";
    echo "Curl Error: $err\n";
    echo "Response:\n$response\n\n";
}
?>
