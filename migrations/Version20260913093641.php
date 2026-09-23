<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260913093641 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE cv_like (
                id INT AUTO_INCREMENT NOT NULL,
                created_at DATETIME NOT NULL,
                recruiter_id INT NOT NULL,
                cv_id INT NOT NULL,
                UNIQUE INDEX uniq_like_recruiter_cv (recruiter_id, cv_id),
                INDEX IDX_CD2DA06F156BE243 (recruiter_id),
                INDEX IDX_CD2DA06FCFE419E2 (cv_id),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE cv_like
                ADD CONSTRAINT FK_CD2DA06F156BE243
                FOREIGN KEY (recruiter_id) REFERENCES users (id)
                ON DELETE CASCADE
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE cv_like
                ADD CONSTRAINT FK_CD2DA06FCFE419E2
                FOREIGN KEY (cv_id) REFERENCES curriculum_vitae (id)
                ON DELETE CASCADE
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cv_like DROP FOREIGN KEY FK_CD2DA06F156BE243');
        $this->addSql('ALTER TABLE cv_like DROP FOREIGN KEY FK_CD2DA06FCFE419E2');
        $this->addSql('DROP TABLE cv_like');
    }
}
