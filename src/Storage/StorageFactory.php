<?php

namespace App\Storage;

use App\Config\AppConfig;

final class StorageFactory
{
    public static function create(AppConfig $config): StorageInterface
    {
        $storageType = $config->get('storage.type', 'local');

        if ($storageType === 'local') {
            return new LocalStorage(
                $config->get('storage.local.base_path')
            );
        }

        if ($storageType === 's3') {
            return new S3Storage(
                $config->get('storage.s3.endpoint'),
                $config->get('storage.s3.region'),
                $config->get('storage.s3.bucket'),
                $config->get('storage.s3.access_key'),
                $config->get('storage.s3.secret_key'),
                $config->get('storage.s3.use_path_style_endpoint', true)
            );
        }

        throw new \RuntimeException('Unsupported storage type: ' . $storageType);
    }
}