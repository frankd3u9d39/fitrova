<?php
$json = file_get_contents('https://generativelanguage.googleapis.com/v1beta/models?key=AQ.Ab8RN6JCMfsoU7_dHDI7TQsk_23gXP2PUnL2Vjj-oPsZuO2y2A');
$data = json_decode($json, true);
foreach ($data['models'] as $m) {
    if (strpos($m['name'], 'flash') !== false) {
        echo $m['name'] . "\n";
    }
}
