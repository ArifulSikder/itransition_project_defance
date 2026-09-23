<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260912152238 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE attribute_value (
                id INT AUTO_INCREMENT NOT NULL,
                raw_value LONGTEXT DEFAULT NULL,
                version INT DEFAULT 1 NOT NULL,
                owner_id INT NOT NULL,
                attribute_id INT NOT NULL,
                UNIQUE INDEX uniq_user_attribute (owner_id, attribute_id),
                INDEX IDX_FE4FBB827E3C61F9 (owner_id),
                INDEX IDX_FE4FBB82B6E62EFA (attribute_id),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_value
                ADD CONSTRAINT FK_FE4FBB827E3C61F9
                FOREIGN KEY (owner_id) REFERENCES users (id)
                ON DELETE CASCADE
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_value
                ADD CONSTRAINT FK_FE4FBB82B6E62EFA
                FOREIGN KEY (attribute_id) REFERENCES attribute_definition (id)
                ON DELETE CASCADE
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attribute_value DROP FOREIGN KEY FK_FE4FBB827E3C61F9');
        $this->addSql('ALTER TABLE attribute_value DROP FOREIGN KEY FK_FE4FBB82B6E62EFA');
        $this->addSql('DROP TABLE attribute_value');
    }
}
