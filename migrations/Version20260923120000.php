<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260923120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Email confirmation tokens, and a MySQL FULLTEXT index for search.';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $users = $schemaManager->introspectTable('users');
        if (!$users->hasColumn('email_verified')) {
            $this->addSql(<<<'SQL'
                ALTER TABLE users
                    ADD email_verified BOOLEAN DEFAULT 1 NOT NULL
                SQL);
        }
        if (!$users->hasColumn('confirmation_token')) {
            $this->addSql(<<<'SQL'
                ALTER TABLE users
                    ADD confirmation_token VARCHAR(64) DEFAULT NULL
                SQL);
            $this->addSql(<<<'SQL'
                CREATE UNIQUE INDEX uniq_users_confirmation_token
                    ON users (confirmation_token)
                SQL);
        }

        if ($this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            $indexes = $schemaManager->listTableIndexes('search_document');
            $hasFulltext = false;
            foreach ($indexes as $name => $index) {
                if (strtolower((string) $name) === 'search_document_fulltext' || strtolower($index->getName()) === 'search_document_fulltext') {
                    $hasFulltext = true;
                }
            }
            if (!$hasFulltext) {
                $this->addSql(<<<'SQL'
                    CREATE FULLTEXT INDEX search_document_fulltext
                        ON search_document (title, body)
                    SQL);
            }
        }
    }

    public function down(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            $this->addSql('DROP INDEX search_document_fulltext ON search_document');
        }

        $this->addSql(<<<'SQL'
            DROP INDEX uniq_users_confirmation_token ON users
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE users
                DROP email_verified,
                DROP confirmation_token
            SQL);
    }
}
