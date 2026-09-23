<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260915234500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop copied CV attribute values; CV content is looked up from the candidate profile.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cv_attribute_value DROP FOREIGN KEY FK_C6F9B43FCFE419E2');
        $this->addSql('ALTER TABLE cv_attribute_value DROP FOREIGN KEY FK_C6F9B43FB6E62EFA');
        $this->addSql('DROP TABLE cv_attribute_value');
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE cv_attribute_value (
                id INT AUTO_INCREMENT NOT NULL,
                text_value LONGTEXT DEFAULT NULL,
                numeric_value DOUBLE PRECISION DEFAULT NULL,
                date_value DATE DEFAULT NULL,
                bool_value TINYINT DEFAULT NULL,
                period_from DATE DEFAULT NULL,
                period_to DATE DEFAULT NULL,
                cv_id INT NOT NULL,
                attribute_id INT NOT NULL,
                UNIQUE INDEX uniq_cv_attribute (cv_id, attribute_id),
                INDEX IDX_C6F9B43FCFE419E2 (cv_id),
                INDEX IDX_C6F9B43FB6E62EFA (attribute_id),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE cv_attribute_value
                ADD CONSTRAINT FK_C6F9B43FCFE419E2
                FOREIGN KEY (cv_id) REFERENCES curriculum_vitae (id)
                ON DELETE CASCADE
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE cv_attribute_value
                ADD CONSTRAINT FK_C6F9B43FB6E62EFA
                FOREIGN KEY (attribute_id) REFERENCES attribute_definition (id)
                ON DELETE CASCADE
            SQL);
    }
}
