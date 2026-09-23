<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260913140912 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE attribute_usage (
                id INT AUTO_INCREMENT NOT NULL,
                used_at DATETIME NOT NULL,
                user_id INT NOT NULL,
                attribute_id INT NOT NULL,
                UNIQUE INDEX uniq_usage_user_attribute (user_id, attribute_id),
                INDEX IDX_33D3BDC6A76ED395 (user_id),
                INDEX IDX_33D3BDC6B6E62EFA (attribute_id),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_usage
                ADD CONSTRAINT FK_33D3BDC6A76ED395
                FOREIGN KEY (user_id) REFERENCES users (id)
                ON DELETE CASCADE
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE attribute_usage
                ADD CONSTRAINT FK_33D3BDC6B6E62EFA
                FOREIGN KEY (attribute_id) REFERENCES attribute_definition (id)
                ON DELETE CASCADE
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attribute_usage DROP FOREIGN KEY FK_33D3BDC6A76ED395');
        $this->addSql('ALTER TABLE attribute_usage DROP FOREIGN KEY FK_33D3BDC6B6E62EFA');
        $this->addSql('DROP TABLE attribute_usage');
    }
}
