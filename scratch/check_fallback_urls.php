<?php
$urls = [
    'Jumping Jacks' => 'https://www.youtube.com/watch?v=VjO-y9oN5C0',
    'Bodyweight Squats' => 'https://www.youtube.com/watch?v=aclHkVaku9U',
    'Mountain Climbers' => 'https://www.youtube.com/watch?v=zT-9L37Ly1k',
    'Plank Hold' => 'https://www.youtube.com/watch?v=pSHjTRCQxIw',
    'Push Ups' => 'https://www.youtube.com/watch?v=IODxDxX7oi4',
    'Dumbbell Goblet Squats' => 'https://www.youtube.com/watch?v=MeIiGibTCIk',
    'Dumbbell Thrusters' => 'https://www.youtube.com/watch?v=Nn7h1s9vKj0',
    'Dumbbell Rows' => 'https://www.youtube.com/watch?v=dFzUj0Q1t28',
    'Dumbbell Chest Press' => 'https://www.youtube.com/watch?v=VmBy73a1SRE'
];

foreach ($urls as $name => $url) {
    $oembedUrl = "https://www.youtube.com/oembed?url=" . urlencode($url) . "&format=json";

    $ch = curl_init($oembedUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "Exercise: $name -> HTTP Code: $httpCode | " . ($httpCode === 200 ? "VALID" : "INVALID (Blocked/Not Embeddable)") . "\n";
}
?>
