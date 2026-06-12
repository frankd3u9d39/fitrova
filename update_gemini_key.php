<?php
require_once 'c:/xampp/htdocs/Fitrova/backend/config/db_config.php';

$newKey = 'AIzaSyBQ8TYJ0rdLdklnK9zi2T0U8RVFDp8wmJI';
$newModel = 'gemini-3.1-flash-lite'; 

try {
    // Check if keys exist
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM system_settings WHERE setting_key = ?");
    
    // Update or Insert Gemini API Key
    $stmt->execute(['ai_gemini_api_key']);
    if ($stmt->fetchColumn() > 0) {
        $updateStmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'ai_gemini_api_key'");
        $updateStmt->execute([$newKey]);
        echo "Updated ai_gemini_api_key.\n";
    } else {
        $insertStmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('ai_gemini_api_key', ?)");
        $insertStmt->execute([$newKey]);
        echo "Inserted ai_gemini_api_key.\n";
    }

    // Update Primary Model to Flash for speed as user is using Flash now
    $stmt->execute(['ai_model_primary']);
    if ($stmt->fetchColumn() > 0) {
        $updateStmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'ai_model_primary'");
        $updateStmt->execute([$newModel]);
        echo "Updated ai_model_primary to $newModel.\n";
    } else {
        $insertStmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('ai_model_primary', ?)");
        $insertStmt->execute([$newModel]);
        echo "Inserted ai_model_primary as $newModel.\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
