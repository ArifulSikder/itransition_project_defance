<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260913111803 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE discussion_post (
                id INT AUTO_INCREMENT NOT NULL,
                content LONGTEXT NOT NULL,
                created_at DATETIME NOT NULL,
                position_id INT NOT NULL,
                author_id INT NOT NULL,
                INDEX IDX_7FE4C0BBDD842E46 (position_id),
                INDEX IDX_7FE4C0BBF675F31B (author_id),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE discussion_post
                ADD CONSTRAINT FK_7FE4C0BBDD842E46
                FOREIGN KEY (position_id) REFERENCES position (id)
                ON DELETE CASCADE
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE discussion_post
                ADD CONSTRAINT FK_7FE4C0BBF675F31B
                FOREIGN KEY (author_id) REFERENCES users (id)
                ON DELETE CASCADE
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE discussion_post DROP FOREIGN KEY FK_7FE4C0BBDD842E46');
        $this->addSql('ALTER TABLE discussion_post DROP FOREIGN KEY FK_7FE4C0BBF675F31B');
        $this->addSql('DROP TABLE discussion_post');
    }
}
