<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version202607011723 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create base schema for users, sessions, files and file_blobs';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE IF NOT EXISTS users (
                id SERIAL PRIMARY KEY,
                login VARCHAR(255) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
            )
        ");

        $this->addSql("
            CREATE TABLE IF NOT EXISTS sessions (
                id SERIAL PRIMARY KEY,
                user_id INT NOT NULL,
                token VARCHAR(255) NOT NULL UNIQUE,
                expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,

                CONSTRAINT fk_sessions_user
                    FOREIGN KEY (user_id)
                    REFERENCES users(id)
                    ON DELETE CASCADE
            )
        ");

        $this->addSql("
            CREATE INDEX IF NOT EXISTS idx_sessions_user_id
            ON sessions(user_id)
        ");

        $this->addSql("
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

        $this->addSql("
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

        $this->addSql("
            CREATE INDEX IF NOT EXISTS idx_files_user_id
            ON files(user_id)
        ");

        $this->addSql("
            CREATE INDEX IF NOT EXISTS idx_files_blob_id
            ON files(blob_id)
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE IF EXISTS files");
        $this->addSql("DROP TABLE IF EXISTS file_blobs");
        $this->addSql("DROP TABLE IF EXISTS sessions");
        $this->addSql("DROP TABLE IF EXISTS users");
    }
}