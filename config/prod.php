<?php

return [
    'app' => [
        'env' => 'prod',
        'debug' => false,
    ],

    'storage' => [
        'type' => 'local',

        'local' => [
            'base_path' => getenv('LOCAL_STORAGE_PATH') ?: '/var/www/html/storage',
        ],

        's3' => [
            'endpoint' => getenv('S3_ENDPOINT') ?: '',
            'region' => getenv('S3_REGION') ?: 'us-east-1',
            'bucket' => getenv('S3_BUCKET') ?: '',
            'access_key' => getenv('S3_ACCESS_KEY') ?: '',
            'secret_key' => getenv('S3_SECRET_KEY') ?: '',
            'use_path_style_endpoint' => true,
        ],
    ],

    'logging' => [
        'level' => 'info',
        'path' => getenv('LOG_PATH') ?: '/var/www/html/var/log/app-prod.log',
    ],
];