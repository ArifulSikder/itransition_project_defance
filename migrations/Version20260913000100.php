<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260913000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'PostgreSQL GIN index for full-text search on search_document.body';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'GIN indexes are PostgreSQL-specific.',
        );

        $this->addSql(<<<'SQL'
            CREATE INDEX IF NOT EXISTS search_document_body_gin
                ON search_document
                USING GIN (to_tsvector('english', body))
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->skipIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'GIN indexes are PostgreSQL-specific.',
        );

        $this->addSql('DROP INDEX IF EXISTS search_document_body_gin');
    }
}
