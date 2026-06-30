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

        throw new \RuntimeException('Unsupported storage type: ' . $storageType);
    }
}