<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260912135106 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE position_access_rule (
                id INT AUTO_INCREMENT NOT NULL,
                operator VARCHAR(255) NOT NULL,
                compare_value VARCHAR(255) NOT NULL,
                position_id INT NOT NULL,
                attribute_id INT NOT NULL,
                INDEX IDX_5D97A8C6DD842E46 (position_id),
                INDEX IDX_5D97A8C6B6E62EFA (attribute_id),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE position_access_rule
                ADD CONSTRAINT FK_5D97A8C6DD842E46
                FOREIGN KEY (position_id) REFERENCES position (id)
                ON DELETE CASCADE
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE position_access_rule
                ADD CONSTRAINT FK_5D97A8C6B6E62EFA
                FOREIGN KEY (attribute_id) REFERENCES attribute_definition (id)
                ON DELETE CASCADE
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE position_access_rule DROP FOREIGN KEY FK_5D97A8C6DD842E46');
        $this->addSql('ALTER TABLE position_access_rule DROP FOREIGN KEY FK_5D97A8C6B6E62EFA');
        $this->addSql('DROP TABLE position_access_rule');
    }
}
