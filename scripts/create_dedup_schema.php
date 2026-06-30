<?php

require __DIR__ . '/../vendor/autoload.php';

$entityManager = require __DIR__ . '/../src/Config/doctrine.php';

$connection = $entityManager->getConnection();

$connection->executeStatement("
    CREATE TABLE IF NOT EXISTS file_blobs (
        id SERIAL PRIMARY KEY,
        hash VARCHAR(64) NOT NULL UNIQUE,
        storage_key VARCHAR(512) NOT NULL UNIQUE,
        size INT NOT NULL,
        mime_type VARCHAR(255) DEFAULT NULL,
        ref_count INT NOT NULL DEFAULT 1,
        created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
    )
");

$connection->executeStatement("
    CREATE TABLE IF NOT EXISTS files (
        id SERIAL PRIMARY KEY,
        user_id INT NOT NULL,
        blob_id INT NOT NULL,
        path VARCHAR(1024) NOT NULL,
        name VARCHAR(255) NOT NULL,
        size INT NOT NULL,
        mime_type VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
        updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,

        CONSTRAINT fk_files_user
            FOREIGN KEY (user_id)
            REFERENCES users(id)
            ON DELETE CASCADE,

        CONSTRAINT fk_files_blob
            FOREIGN KEY (blob_id)
            REFERENCES file_blobs(id)
            ON DELETE RESTRICT,

        CONSTRAINT uniq_files_user_path
            UNIQUE (user_id, path)
    )
");

$connection->executeStatement("
    CREATE INDEX IF NOT EXISTS idx_files_user_id
    ON files(user_id)
");

$connection->executeStatement("
    CREATE INDEX IF NOT EXISTS idx_files_blob_id
    ON files(blob_id)
");

echo 'Deduplication tables created successfully.' . PHP_EOL;