<?php

return [
    'app' => [
        'env' => 'dev',
        'debug' => true,
    ],

    'storage' => [
        'type' => 'local',

        'local' => [
            'base_path' => __DIR__ . '/../storage',
        ],

        's3' => [
            'endpoint' => getenv('S3_ENDPOINT') ?: 'http://minio:9000',
            'region' => getenv('S3_REGION') ?: 'us-east-1',
            'bucket' => getenv('S3_BUCKET') ?: 'file-server',
            'access_key' => getenv('S3_ACCESS_KEY') ?: '',
            'secret_key' => getenv('S3_SECRET_KEY') ?: '',
            'use_path_style_endpoint' => true,
        ],
    ],

    'logging' => [
        'level' => 'debug',
        'path' => __DIR__ . '/../var/log/app-dev.log',
    ],
];