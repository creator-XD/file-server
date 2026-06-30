<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Config\AppConfig;
use App\Storage\StorageFactory;

$config = AppConfig::load();
$storage = StorageFactory::create($config);

$content = 'Hello blob storage';
$hash = hash('sha256', $content);

$storageKey = 'blobs/' . substr($hash, 0, 2) . '/' . substr($hash, 2, 2) . '/' . $hash;

echo 'Storage type: ' . $config->get('storage.type') . PHP_EOL;
echo 'Storage key: ' . $storageKey . PHP_EOL;

if (!$storage->blobExists($storageKey)) {
    $storage->saveBlob($storageKey, $content);
    echo 'Blob saved.' . PHP_EOL;
} else {
    echo 'Blob already exists.' . PHP_EOL;
}

$loadedContent = $storage->readBlob($storageKey);

if ($loadedContent !== $content) {
    throw new RuntimeException('Loaded content is not equal to original content');
}

echo 'Blob read successfully.' . PHP_EOL;

$storage->deleteBlob($storageKey);

if ($storage->blobExists($storageKey)) {
    throw new RuntimeException('Blob was not deleted');
}

echo 'Blob deleted successfully.' . PHP_EOL;
