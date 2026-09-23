<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260912131822 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE position (
                id INT AUTO_INCREMENT NOT NULL,
                title VARCHAR(180) NOT NULL,
                short_description LONGTEXT NOT NULL,
                company VARCHAR(160) DEFAULT NULL,
                level VARCHAR(255) NOT NULL,
                public_access TINYINT NOT NULL,
                max_projects INT NOT NULL,
                cv_count INT NOT NULL,
                version INT DEFAULT 1 NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE position');
    }
}
