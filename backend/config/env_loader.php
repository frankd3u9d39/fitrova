<?php
/**
 * Simple .env loader for Fitrova Backend
 */
function loadEnv($path) {
    if (!file_exists($path)) {
        return false;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;

        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) continue;

        $name = trim($parts[0]);
        $value = trim($parts[1]);

        // Remove quotes if present
        $value = trim($value, '"\'');

        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
    return true;
}
?>
