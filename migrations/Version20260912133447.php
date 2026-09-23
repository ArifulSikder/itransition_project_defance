<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260912133447 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE position_attribute (
                id INT AUTO_INCREMENT NOT NULL,
                sort_order INT NOT NULL,
                position_id INT NOT NULL,
                attribute_id INT NOT NULL,
                UNIQUE INDEX uniq_position_attribute (position_id, attribute_id),
                INDEX IDX_AF5BEE86DD842E46 (position_id),
                INDEX IDX_AF5BEE86B6E62EFA (attribute_id),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE position_attribute
                ADD CONSTRAINT FK_AF5BEE86DD842E46
                FOREIGN KEY (position_id) REFERENCES position (id)
                ON DELETE CASCADE
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE position_attribute
                ADD CONSTRAINT FK_AF5BEE86B6E62EFA
                FOREIGN KEY (attribute_id) REFERENCES attribute_definition (id)
                ON DELETE CASCADE
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE position_attribute DROP FOREIGN KEY FK_AF5BEE86DD842E46');
        $this->addSql('ALTER TABLE position_attribute DROP FOREIGN KEY FK_AF5BEE86B6E62EFA');
        $this->addSql('DROP TABLE position_attribute');
    }
}
