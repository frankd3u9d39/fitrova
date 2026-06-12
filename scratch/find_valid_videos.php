<?php
$list = [
    'Jumping Jacks' => [
        '1B9OhyerUBg', 'U4s4mEQ5628', 'UpH72VJD2yY', 'n1a4R1S4L5g', 'HqA284N6pLM',
        '2W4kp0n1bHU', 'gC_L9qAHML8', 'i0Xn63w3cWA', 'wX-7119pL-U', 'VjO-y9oN5C0',
        'ERA88eF6uSM', 'Xm8pDkP6r5s', 'q3n5xG6x6aU', 'tB_S7S5K130', 'v7ayEa31b4s'
    ],
    'Dumbbell Goblet Squats' => [
        'QA0R420uP8E', 'mP1L_xRshQ4', '5Y4HhR7c5mU', 'uB-2DqC4cAg', 'MeIiGibTCIk',
        'dqQG3GZ-Lws', 'gvaqQZ3qQkY', 'OPX5qB6mS4k', 'ZvdK2dF-338', 'v_c67v_W728'
    ],
    'Dumbbell Thrusters' => [
        'M77U4Wb_bM8', 'U4q0D0_z79U', 'f82s2n3-09g', 'Rst-a1E2uU0', 'Nn7h1s9vKj0',
        'p4mZ6u2Q2w8', 'v79e3T7S6kM', '8zT9k8Q8C4A', 'uDqQ6O5zU8A', '7mQ4QZ8C4g8'
    ],
    'Dumbbell Rows' => [
        '5PoEkDK0H5c', 'dK296c0x2Yw', 'bM3B1QhL5_U', '6gvhHhEfC2o', 'dFzUj0Q1t28',
        'EEFHH_N9c8U', 'roCP6u9-5Ok', '7mQ4QZ8C4g8', '9cQ4d8C4g8B', 'u6V3aT7_4b8'
    ],
    'Dumbbell Chest Press' => [
        'm8wG7D43u90', 'vthMCtgUtmY', '1bS9XhKq0e8', 'bY9oJmG37bA', 'VmBy73a1SRE',
        '4h6bC8G2_8A', 'v89Q4d8C4g8', 'vDqQ6O5zU8A', '7mQ4QZ8C4g8', 'xsG2v_C_88A'
    ]
];

foreach ($list as $name => $ids) {
    echo "=== Testing $name ===\n";
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
        
        if ($code === 200) {
            echo "  [FOUND] $id is VALID!\n";
        }
    }
}
?>
