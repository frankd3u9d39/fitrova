<?php
require_once __DIR__ . '/../backend/config/db_config.php';

// Get settings
$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
$settings = $settingsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
$ytApiKey = $settings['ai_youtube_api_key'] ?? '';

if (empty($ytApiKey)) {
    die("Error: YouTube API Key is empty.\n");
}

$exercises = ['Jumping Jacks', 'Mountain Climbers', 'Dumbbell Goblet Squats', 'Dumbbell Thrusters', 'Dumbbell Rows', 'Dumbbell Chest Press', 'Plank Shoulder Taps'];

function isEmbeddable($url) {
    $oembedUrl = "https://www.youtube.com/oembed?url=" . urlencode($url) . "&format=json";
    $ch = curl_init($oembedUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code === 200;
}

foreach ($exercises as $exercise) {
    echo "=== Searching for: $exercise ===\n";
    $query = urlencode($exercise . " exercise tutorial");
    $url = "https://www.googleapis.com/youtube/v3/search?part=snippet&maxResults=8&q=$query&type=video&videoEmbeddable=true&key=$ytApiKey";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if (!empty($data['items'])) {
            $found = false;
            foreach ($data['items'] as $item) {
                $videoId = $item['id']['videoId'] ?? null;
                if (!$videoId) continue;
                
                $videoUrl = "https://www.youtube.com/watch?v=" . $videoId;
                $title = $item['snippet']['title'] ?? '';
                
                if (isEmbeddable($videoUrl)) {
                    echo "  [FOUND] ID: $videoId | Title: $title | Url: $videoUrl\n";
                    $found = true;
                    // Let's find at least 2 working ones just in case
                } else {
                    echo "  [BLOCKED] ID: $videoId | Title: $title\n";
                }
            }
            if (!$found) {
                echo "  No embeddable videos found for: $exercise\n";
            }
        } else {
            echo "  No search results returned from API.\n";
        }
    } else {
        echo "  YouTube API search failed with HTTP code: $httpCode. Response: " . substr($response, 0, 300) . "\n";
    }
}
?>
