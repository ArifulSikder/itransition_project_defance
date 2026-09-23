<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260912114209 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE attribute_definition (
                id INT AUTO_INCREMENT NOT NULL,
                code VARCHAR(64) DEFAULT NULL,
                name VARCHAR(160) NOT NULL,
                category VARCHAR(255) NOT NULL,
                description LONGTEXT DEFAULT NULL,
                type VARCHAR(255) NOT NULL,
                options JSON DEFAULT NULL,
                builtin TINYINT NOT NULL,
                min_length INT DEFAULT NULL,
                max_length INT DEFAULT NULL,
                pattern VARCHAR(255) DEFAULT NULL,
                min_value DOUBLE PRECISION DEFAULT NULL,
                max_value DOUBLE PRECISION DEFAULT NULL,
                version INT DEFAULT 1 NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE INDEX uniq_attribute_name (name),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE attribute_definition');
    }
}
