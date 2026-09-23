# CV Hub commands

Run every command from the project root (`clonner`). PHP 8.2 or newer, Composer, and MySQL 8 are required. The database name in `.env` is `cv_hub`.

## First setup

```bash
composer install
```

Check that `.env` has a MySQL URL. Copy it to `.env.local` when you need a different host, user, or password. `.env.local` is not committed and overrides `.env`.

```text
DATABASE_URL="mysql://root:password@127.0.0.1:3306/cv_hub?serverVersion=8.4.3&charset=utf8mb4"
```

Optional values, also in `.env.local`:

```text
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=
CLOUDINARY_CLOUD_NAME=
CLOUDINARY_UPLOAD_PRESET=
MAILER_DSN=null://null
```

`MAILER_DSN=null://null` does not send mail. Registration still works: the confirmation page shows the link. Set a real DSN when you want email to leave the machine.

Create the database, apply every migration, and load the demo accounts:

```bash
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:fixtures:load --no-interaction
```

Demo password for every account is `password`.

| Email | Role |
| --- | --- |
| admin@cvhub.test | Administrator |
| recruiter@cvhub.test | Recruiter |
| john@cvhub.test | Candidate |
| lee@cvhub.test | Candidate |
| paul@cvhub.test | Candidate |

## Run the application

```bash
php -S 127.0.0.1:8000 -t public
```

Open http://127.0.0.1:8000. A static check page is at http://127.0.0.1:8000/hello.html.

Stop the server with Ctrl+C.

If the Symfony CLI is installed, this is the same thing:

```bash
symfony server:start
```

## Cache

Clear the dev cache after changing config, entities, or translations:

```bash
php bin/console cache:clear
```

Production cache:

```bash
php bin/console cache:clear --env=prod
```

## Database

Create the empty database named in `DATABASE_URL`:

```bash
php bin/console doctrine:database:create --if-not-exists
```

Drop it. This deletes every table and row:

```bash
php bin/console doctrine:database:drop --force --if-exists
```

Check that entity mappings match the database:

```bash
php bin/console doctrine:schema:validate
```

Show the SQL Doctrine would run to match the entities, without executing it:

```bash
php bin/console doctrine:schema:update --dump-sql
```

Run one SQL statement:

```bash
php bin/console dbal:run-sql "SHOW TABLES"
```

Use migrations for schema changes. Do not mix `doctrine:schema:create` with `doctrine:migrations:migrate` on the same empty database: both try to create the same tables.

## Migrations

Files live in `migrations/` and use the namespace `DoctrineMigrations`.

### See what has run

```bash
php bin/console doctrine:migrations:status
php bin/console doctrine:migrations:list
php bin/console doctrine:migrations:current
php bin/console doctrine:migrations:latest
```

### Create a migration

After you change an entity, generate a migration from the difference between the entities and the database:

```bash
php bin/console make:migration
```

The same diff without the Maker bundle:

```bash
php bin/console doctrine:migrations:diff
```

An empty migration class, when you want to write the SQL yourself:

```bash
php bin/console doctrine:migrations:generate
```

Open the new file and break each SQL statement across lines, one column or constraint per line:

```php
$this->addSql(<<<'SQL'
    ALTER TABLE users
        ADD email_verified BOOLEAN DEFAULT 1 NOT NULL
    SQL);
```

### Run migrations up

Apply every migration that has not run yet:

```bash
php bin/console doctrine:migrations:migrate --no-interaction
```

Without `--no-interaction`, Doctrine asks for confirmation.

Preview the SQL and do not execute it:

```bash
php bin/console doctrine:migrations:migrate --dry-run
```

Move forward to one version:

```bash
php bin/console doctrine:migrations:migrate DoctrineMigrations\\Version20260923120000 --no-interaction
```

### Run migrations down

Roll back the latest migration:

```bash
php bin/console doctrine:migrations:migrate prev --no-interaction
```

Roll back to a chosen version. Migrations after that version run their `down()` method:

```bash
php bin/console doctrine:migrations:migrate DoctrineMigrations\\Version20260912101843 --no-interaction
```

Roll back everything:

```bash
php bin/console doctrine:migrations:migrate first --no-interaction
```

Run `down()` for one version only:

```bash
php bin/console doctrine:migrations:execute --down DoctrineMigrations\\Version20260923120000 --no-interaction
```

Run `up()` for one version that was skipped:

```bash
php bin/console doctrine:migrations:execute --up DoctrineMigrations\\Version20260923120000 --no-interaction
```

### Fresh database

```bash
php bin/console doctrine:database:drop --force --if-exists
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:fixtures:load --no-interaction
```

`doctrine:fixtures:load` empties the tables before inserting demo data. Confirm with `--no-interaction` only when that is what you want.

## Fixtures

```bash
php bin/console doctrine:fixtures:load --no-interaction
```

Append demo rows without deleting what is already there:

```bash
php bin/console doctrine:fixtures:load --append --no-interaction
```

## Useful console commands

List routes:

```bash
php bin/console debug:router
```

One route:

```bash
php bin/console debug:router cv_pdf
```

Check that services can be built:

```bash
php bin/console lint:container
```

Check Twig, YAML, and translations:

```bash
php bin/console lint:twig templates
php bin/console lint:yaml config translations
```

Symfony and library versions:

```bash
php bin/console about
```

## Assets and translations

Install front-end packages declared in `importmap.php`:

```bash
php bin/console importmap:install
```

Copy bundle assets into `public/`:

```bash
php bin/console assets:install
```

UI strings are in `translations/messages.en.yaml` and `translations/messages.bn.yaml`. After editing them:

```bash
php bin/console cache:clear
```

## Composer

Install dependencies:

```bash
composer install
```

Add a package:

```bash
composer require vendor/package
```

Update dependencies:

```bash
composer update
```
