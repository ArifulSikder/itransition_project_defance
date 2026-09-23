<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260912154702 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE project (
                id INT AUTO_INCREMENT NOT NULL,
                name VARCHAR(200) NOT NULL,
                started_on DATE NOT NULL,
                ended_on DATE DEFAULT NULL,
                description LONGTEXT NOT NULL,
                version INT DEFAULT 1 NOT NULL,
                owner_id INT NOT NULL,
                INDEX IDX_2FB3D0EE7E3C61F9 (owner_id),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE project_tag (
                project_id INT NOT NULL,
                tag_id INT NOT NULL,
                INDEX IDX_91F26D60166D1F9C (project_id),
                INDEX IDX_91F26D60BAD26311 (tag_id),
                PRIMARY KEY (project_id, tag_id)
            ) DEFAULT CHARACTER SET utf8mb4
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE project
                ADD CONSTRAINT FK_2FB3D0EE7E3C61F9
                FOREIGN KEY (owner_id) REFERENCES users (id)
                ON DELETE CASCADE
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE project_tag
                ADD CONSTRAINT FK_91F26D60166D1F9C
                FOREIGN KEY (project_id) REFERENCES project (id)
                ON DELETE CASCADE
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE project_tag
                ADD CONSTRAINT FK_91F26D60BAD26311
                FOREIGN KEY (tag_id) REFERENCES tag (id)
                ON DELETE CASCADE
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project DROP FOREIGN KEY FK_2FB3D0EE7E3C61F9');
        $this->addSql('ALTER TABLE project_tag DROP FOREIGN KEY FK_91F26D60166D1F9C');
        $this->addSql('ALTER TABLE project_tag DROP FOREIGN KEY FK_91F26D60BAD26311');
        $this->addSql('DROP TABLE project_tag');
        $this->addSql('DROP TABLE project');
    }
}
