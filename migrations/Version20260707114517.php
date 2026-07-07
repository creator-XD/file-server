<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260707114517 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
        "ALTER TABLE users ADD COLUMN plan VARCHAR(20) NOT NULL DEFAULT 'free'"
        );

    }

    public function down(Schema $schema): void
    {
         $this->addSql(
        'ALTER TABLE users DROP COLUMN plan'
        );

    }
}
