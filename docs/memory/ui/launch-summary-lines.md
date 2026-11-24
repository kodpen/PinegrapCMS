---
name: launch-summary-lines
description: The agency summary's "What we need from you" lines table launch_summary_lines (SJ1b, 2026.11.0 step 11.0.274): its columns, why no person is held, what it does not store
---

## Where is the table made?
- `upgrade_2026_11_0_launch_summary_lines()` in `includes/migrations/2026.11.0.php`, label 11.0.274, listed in `get_tables()` (`install/index.php`). Tests: `tests/launch_summary_lines_schema_test.php` (columns, keys, label, a second run writes nothing).
- Made because the owner said yes to the set-up journey's one table (poll 206, `workflow/decisions-made-schema-46.md`); the columns are brought to the architect in the step's PR before it is marked ready.

## Which columns does it have?
- `id`; `line_text` VARCHAR(500), the line the agency wrote (logo, texts, domain access); `answer` TEXT NULL, the customer's text answer, NULL while unanswered; `answered_at`; `sort_order`, the line's place in the drawer and on the customer's page (`KEY idx_order`); `created_by` (the panel user who wrote it, the bell line's addressee in SJ5); `created_at`, `updated_at`. Times are Unix seconds, 0 when not set.

## What is not in it?
- The summary link's hash and expiry, the site's purpose, the go-live time and hidden steps: one JSON row in `config_text` (`launch-journey.md`), no column anywhere.
- No person: the customer answers through the signed link, not as a user or a contact, so the table is not on the privacy lists or `pg_contact_holders()`. An answer is free text the agency asked for.
- A line is never a setting's value; the customer's page shows only the lines and the steps' states.

Related: docs/memory/ui/launch-journey-catalogue.md, docs/memory/ui/launch-journey.md
