<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260913091422 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE curriculum_vitae (
                id INT AUTO_INCREMENT NOT NULL,
                published TINYINT NOT NULL,
                like_count INT NOT NULL,
                version INT DEFAULT 1 NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                owner_id INT NOT NULL,
                position_id INT NOT NULL,
                UNIQUE INDEX uniq_cv_user_position (owner_id, position_id),
                INDEX IDX_1FC998447E3C61F9 (owner_id),
                INDEX IDX_1FC99844DD842E46 (position_id),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE curriculum_vitae
                ADD CONSTRAINT FK_1FC998447E3C61F9
                FOREIGN KEY (owner_id) REFERENCES users (id)
                ON DELETE CASCADE
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE curriculum_vitae
                ADD CONSTRAINT FK_1FC99844DD842E46
                FOREIGN KEY (position_id) REFERENCES position (id)
                ON DELETE CASCADE
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE curriculum_vitae DROP FOREIGN KEY FK_1FC998447E3C61F9');
        $this->addSql('ALTER TABLE curriculum_vitae DROP FOREIGN KEY FK_1FC99844DD842E46');
        $this->addSql('DROP TABLE curriculum_vitae');
    }
}
