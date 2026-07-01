<?php

use Doctrine\DBAL\DriverManager;

require_once __DIR__ . '/vendor/autoload.php';

return DriverManager::getConnection([
    'driver' => 'pdo_pgsql',
    'host' => getenv('DB_HOST') ?: 'db',
    'port' => getenv('DB_PORT') ?: 5432,
    'dbname' => getenv('DB_NAME') ?: 'file_server',
    'user' => getenv('DB_USER') ?: 'file_user',
    'password' => getenv('DB_PASSWORD') ?: 'file_password',
]);