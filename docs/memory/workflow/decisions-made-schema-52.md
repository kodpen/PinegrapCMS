---
name: decisions-made-schema-52
description: Decisions list, schema part 52 (2026-11-24 on): the table form_old_address keeps a record's earlier address names, so a renamed post or category answers its old address with a 301
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-schema-51.md`.

## Does a renamed record keep its old address by a table? (owner, 2026-11-24, in the architect's window, bug #11439)
- Yes. Asked with three options (a table of earlier names; a 301 short link at every rename, no schema change; as today), he chose "Eski adlar tablosu".
- Before: every save rewrote `forms.address_name`; `/<page>/<old name>` answered 404 and `?category=<old name>` listed nothing and turned the page noindex.
- Why not short links: rows the site owner did not make fill the Short links screen, renames chain, and `?category=` is not reached.
- Running sites: names are kept from the upgrade on, with no backfill. Import redirects (`platform/import-redirects.md`) stay as they are.

## Which columns does form_old_address have? (architect, 2026-11-24)
- One row an earlier address name of a record: `id`; `form_id` (`forms.id`); `page_id` (`forms.page_id`, the custom form's page); `address_name` VARCHAR(255), collation as `forms.address_name`; `created_at` as Unix seconds.
- Keys: PRIMARY KEY (`id`), `idx_lookup` (`page_id`, `address_name`(191)), `idx_form` (`form_id`).
- A migration step of 2026.11.0 (`release/how-to-add-migration.md`), the table listed in `get_tables()`. A change of these columns comes to the architect before the PR is marked ready.

## When is a row written and removed? (architect, 2026-11-24)
- Written only in `update_submitted_form_address_name()`, when a complete record's name changes from a name that is not empty. At most 20 rows a record; the oldest is dropped.
- A live name always wins: a record taking a name removes that name's rows on the same form page (its own when it is renamed back, another record's too), and every lookup asks the live names first.
- Rows go with their record (delete, erasure). A row whose record is gone answers as an unknown name.

## What does an old name answer? (architect, 2026-11-24)
- `/<page>/<old name>`: 301 to the record's address of today, under every check `pg_sw_route_record_address()` makes for a live name (public view, folder access, filters), so an old name tells no more than a live one. An old name never draws a page.
- `pg_coll_reference_lookup()` matches an old name after the live ones, so `?category=<old name>` lists as the name of today does.
