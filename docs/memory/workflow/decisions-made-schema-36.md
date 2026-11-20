---
name: decisions-made-schema-36
description: Decisions list, schema part 36 (2026-11-20 on): the privacy_requests table of plan privacy-centre, its columns, and destruction records kept as audit_log lines
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-schema-35.md`.

## Does the request box get a table? (owner, 2026-11-20, agent channel poll 177, channel message 10555)
- "Evet, tablo eklensin": one new table, `privacy_requests`, for a person's requests with their 30 days.
- Not chosen: "Hayır, başvuru kutusu sonraya kalsın" (no schema, requests keep coming by e-mail).
- Destruction records get no table: they are `audit_log` lines, as the erase of one person writes one today.

## Which columns does it have? (architect, 2026-11-20, channel message 10557)
- `id` INT UNSIGNED AUTO_INCREMENT; `kind`, `status`, `source` (form, account, staff), `result` VARCHAR(16); `contact_id`, `user_id`, `assigned_user_id`, `created_by` INT UNSIGNED; `email` and `name` as `contacts` types them; `message` TEXT; `answer` MEDIUMTEXT.
- `verified_at`, `received_at`, `due_at`, `closed_at` INT UNSIGNED; `reminded` TINYINT UNSIGNED.
- Keys: (`status`, `due_at`); `contact_id`; `email`.
- The 30 days start when the address is proven (`received_at`); a row not proven is not in the box and is swept after 7 days.
- A request's steps are `audit_log` lines (`entity_type = 'privacy_request'`), not columns.
- The table is in the inventory's catalogue and in `pg_privacy_erase_tables()` (anonymise).
- The worker of the step that adds it writes the columns into its PR body and asks the architect before it is marked ready.

## Where do destruction records live? (architect, 2026-11-20, channel message 10557)
- In `audit_log`: one line per rule and run (`entity_type = 'privacy_rule'`, the counts in `after_json`, no person), beside the erase line of one person.
- The daily trim keeps these lines and the erase lines 3 years, whatever `audit_log_retention_years` says; the Audit Trail settings card says so.

Related: docs/memory/plans/privacy-centre.md, docs/memory/workflow/decisions-made-product-87.md
