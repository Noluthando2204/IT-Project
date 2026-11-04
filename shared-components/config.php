<?php
declare(strict_types=1);

// Optional ignored local configuration; deployment environment always takes priority.
$local_config = dirname(__DIR__) . '/database-config/local.php';
if (is_file($local_config)) {
    $settings = require $local_config;
    if (is_array($settings)) {
        foreach ($settings as $key => $value) {
            if (is_string($key) && preg_match('/^[A-Z][A-Z0-9_]*$/D', $key)
                && is_scalar($value) && getenv($key) === false) {
                putenv($key . '=' . (string) $value);
            }
        }
    }
    unset($settings);
}
