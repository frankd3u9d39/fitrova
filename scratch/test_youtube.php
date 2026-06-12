<?php
$candidates = [
    'Jumping Jacks' => ['gC_L9qAHML8', 'i0Xn63w3cWA', 'wX-7119pL-U', '2W4kp0n1bHU'],
    'Mountain Climbers' => ['kLh-5ElvIJo', 'de3Gb-0TkaA', 'dhZLH85-Y50', 'cnyTQDSE884'],
    'Dumbbell Goblet Squats' => ['QA0R420uP8E', 'mP1L_xRshQ4', '5Y4HhR7c5mU', 'uB-2DqC4cAg'],
    'Dumbbell Thrusters' => ['M77U4Wb_bM8', 'U4q0D0_z79U', 'f82s2n3-09g', 'Rst-a1E2uU0'],
    'Dumbbell Rows' => ['5PoEkDK0H5c', 'dK296c0x2Yw', 'bM3B1QhL5_U', '6gvhHhEfC2o'],
    'Dumbbell Chest Press' => ['m8wG7D43u90', 'vthMCtgUtmY', '1bS9XhKq0e8', 'bY9oJmG37bA']
];

foreach ($candidates as $name => $ids) {
    echo "=== Candidates for $name ===\n";
    foreach ($ids as $id) {
        $url = "https://www.youtube.com/watch?v=" . $id;
        $oembed = "https://www.youtube.com/oembed?url=" . urlencode($url) . "&format=json";
        
        $ch = curl_init($oembed);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "  $id: HTTP $code | " . ($code === 200 ? "VALID (Embeddable)" : "INVALID") . "\n";
    }
}
?>
