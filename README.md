# CV Hub

Symfony 7 recruitment platform for the PHP course project. Recruiters design **position templates** from a reusable **attribute library**. Candidates keep one profile and generate **CVs** for the positions they can access.

Command reference (setup, server, migrations up and down, fixtures): [COMMANDS.md](COMMANDS.md).

## Run it

```bash
composer install
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:schema:create
php bin/console doctrine:migrations:migrate --no-interaction   # adds the Postgres search index
php bin/console doctrine:fixtures:load --no-interaction
php -S 127.0.0.1:8000 -t public
```

Open http://127.0.0.1:8000 and the static health check at http://127.0.0.1:8000/hello.html

## Demo accounts

Password for all: `password`

| Email | Role |
| --- | --- |
| admin@cvhub.test | Administrator |
| recruiter@cvhub.test | Recruiter |
| john@cvhub.test | Candidate (matches the restricted Data Scientist rule) |
| lee@cvhub.test | Candidate |
| paul@cvhub.test | Candidate (restricted positions stay hidden) |

## What to click first

1. Guest: home statistics, latest/popular position **tables**, tag cloud, header search.
2. Sign in as `recruiter@cvhub.test`: Attribute library, edit a position, open a published CV, like it, post in Discussion.
3. Sign in as `john@cvhub.test`: Profile (Me / Info / Projects / CVs) auto-saves every 8 seconds, generate or edit a CV, publish when every field is filled.
4. Admin: `/admin/users` — block, roles, delete. Toolbar actions, no buttons in table rows.

## Libraries (used, not copied)

| Need | Library |
| --- | --- |
| PHP app / ORM | Symfony 7.4, Doctrine |
| UI | Bootstrap 5, Bootstrap Icons |
| Markdown | `league/commonmark` + EasyMDE + Twig markdown extra |
| Tags | Tom Select |
| Images | Cloudinary unsigned upload from the browser (never stored on this server) |
| Social login | `knpuniversity/oauth2-client-bundle` + Google + GitHub |
| Full-text search | MySQL `MATCH ... AGAINST`, or PostgreSQL `to_tsvector` |
| Excel export | PhpSpreadsheet |
| QR / PDF | Endroid QR Code + Dompdf |
| Pagination | KnpPaginator |

Set `CLOUDINARY_CLOUD_NAME` and `CLOUDINARY_UPLOAD_PRESET` in `.env.local` for drag-and-drop photos. Without them the UI asks for an external image URL.

Set Google / GitHub OAuth client IDs the same way. Form registration sends a confirmation email before the account can sign in. With the default `MAILER_DSN=null://null`, the confirmation page shows the link so you can open it locally. Demo accounts are already confirmed.

## How the three killer features work

1. **Attribute library** — `AttributeDefinition` is the single catalog. Prefix lookup + category filter + recently used (`AttributeUsage`). Built-in fields (name, location, photo) use the same entity with `builtin = true`.
2. **Positions** — a position is a template: ordered attributes, project-tag filter, max projects, optional access rules. Rules are evaluated in `PositionAccessEvaluator` against the candidate's stored values. All recruiters share the same pool (no ownership).
3. **CV generation** — pressing Create CV stores a CV *row* (id, owner, position, published, likes, version). The **content is looked up**, not copied: template attributes join `attribute_value` (one value per candidate per attribute) and projects are filtered by tags. Edit English on the profile or on any CV — it changes everywhere. A tighter access rule only **hides** the CV in the UI; the row stays. Recruiters still get a compatible table (sort, filter, aggregates) because every CV for a position shares the same attribute ids. Client JSON is the save payload, never stored CV content.

## Optimistic locking

`User`, `AttributeDefinition`, `Position`, `AttributeValue`, and `CurriculumVitae` have a Doctrine `@Version` column. Attribute edits (profile or CV) send the **candidate** version the client last saw, because there is one value per attribute. `OptimisticLocker` maps a mismatch to HTTP 409. Autosave shows a reload prompt and does not overwrite the newer row. HTML forms flash the same message and send you back.

## Tables, not tiles

Positions, CVs, users, and attributes are HTML tables. Row click opens the record. Edit / delete / role actions live in a **toolbar** that appears when checkboxes are selected.

## Languages and themes

English + Bangla (`/preferences/locale/en|bn`). Light + dark (`data-bs-theme`, `/preferences/theme/light|dark`). The choice is stored on the user (and in the session for guests). Only the UI is translated.

## Discussions and likes

Discussion tab polls `/positions/{id}/discussion/feed` every 3 seconds and **appends** new posts. Recruiters' author names link to the public profile. Only recruiters can like a CV, at most once.

## Project layout

```
src/Entity          domain model
src/Repository      targeted queries (no SELECT *, no queries in loops)
src/Service         access rules, CV assembly, search index, exports
src/Controller      HTTP layer
src/Security        voters, social login, blocked-user check
templates           Twig + Bootstrap
assets/app.js       tables, auto-save, pickers, Cloudinary, polling
```
