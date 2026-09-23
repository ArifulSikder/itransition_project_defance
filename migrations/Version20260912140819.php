<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260912140819 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE position_project_tag (
                position_id INT NOT NULL,
                tag_id INT NOT NULL,
                INDEX IDX_7C640F6CDD842E46 (position_id),
                INDEX IDX_7C640F6CBAD26311 (tag_id),
                PRIMARY KEY (position_id, tag_id)
            ) DEFAULT CHARACTER SET utf8mb4
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE position_project_tag
                ADD CONSTRAINT FK_7C640F6CDD842E46
                FOREIGN KEY (position_id) REFERENCES position (id)
                ON DELETE CASCADE
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE position_project_tag
                ADD CONSTRAINT FK_7C640F6CBAD26311
                FOREIGN KEY (tag_id) REFERENCES tag (id)
                ON DELETE CASCADE
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE position_project_tag DROP FOREIGN KEY FK_7C640F6CDD842E46');
        $this->addSql('ALTER TABLE position_project_tag DROP FOREIGN KEY FK_7C640F6CBAD26311');
        $this->addSql('DROP TABLE position_project_tag');
    }
}
