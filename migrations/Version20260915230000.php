<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260915230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store CV attribute values as relational rows referencing the attribute library.';
    }

    public function up(Schema $schema): void
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
        $this->addSql(<<<'SQL'
            INSERT INTO cv_attribute_value (text_value, numeric_value, date_value, bool_value, period_from, period_to, cv_id, attribute_id)
            SELECT
                CASE WHEN a.type IN ('string', 'text', 'image', 'choice') THEN av.raw_value ELSE NULL END,
                CASE WHEN a.type = 'numeric' AND av.raw_value IS NOT NULL AND av.raw_value <> '' THEN CAST(av.raw_value AS DECIMAL(16, 6)) ELSE NULL END,
                CASE WHEN a.type = 'date' AND av.raw_value IS NOT NULL AND av.raw_value <> '' THEN CAST(av.raw_value AS DATE) ELSE NULL END,
                CASE
                    WHEN a.type = 'boolean' AND av.raw_value IN ('1', 'true', 'yes', 'on') THEN 1
                    WHEN a.type = 'boolean' AND av.raw_value IN ('0', 'false', 'no', 'off') THEN 0
                    ELSE NULL
                END,
                CASE WHEN a.type = 'period' THEN NULLIF(JSON_UNQUOTE(JSON_EXTRACT(av.raw_value, '$.from')), '') ELSE NULL END,
                CASE WHEN a.type = 'period' THEN NULLIF(JSON_UNQUOTE(JSON_EXTRACT(av.raw_value, '$.to')), '') ELSE NULL END,
                cv.id,
                a.id
            FROM curriculum_vitae cv
            INNER JOIN position_attribute pa ON pa.position_id = cv.position_id
            INNER JOIN attribute_definition a ON a.id = pa.attribute_id
            LEFT JOIN attribute_value av ON av.owner_id = cv.owner_id AND av.attribute_id = a.id
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cv_attribute_value DROP FOREIGN KEY FK_C6F9B43FCFE419E2');
        $this->addSql('ALTER TABLE cv_attribute_value DROP FOREIGN KEY FK_C6F9B43FB6E62EFA');
        $this->addSql('DROP TABLE cv_attribute_value');
    }
}
