<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Config\AppConfig;

$config = AppConfig::load();

echo 'APP_ENV: ' . $config->get('app.env') . PHP_EOL;
echo 'Debug: ' . ($config->get('app.debug') ? 'true' : 'false') . PHP_EOL;
echo 'Storage type: ' . $config->get('storage.type') . PHP_EOL;
echo 'Local storage path: ' . $config->get('storage.local.base_path') . PHP_EOL;
echo 'Log level: ' . $config->get('logging.level') . PHP_EOL;
echo 'Log path: ' . $config->get('logging.path') . PHP_EOL;