---
name: privacy-centre-steps
description: Plan privacy-centre, steps KV1 to KV7: the catalogue and transfers, the screen and downloads, the retention rules, the request record, the public form, API, close-out
---

## Which steps are there?
- KV1 (M) first: the catalogue and transfers, no schema. Done (#10738).
  - New `includes/fn/privacy_inventory.php` (pure catalogue, live counts), country and data fields on `includes/fn/connections_registry.php`'s rows, `tests/privacy_inventory_test.php` red first (P5's two failures). Features: P1, P2, P3, P5.
- KV2 (S) after KV1: Contacts › Privacy centre beside the erase screen, the link from Site Settings › Visitors and privacy, Excel through `includes/fn/xlsx_write.php` and PDF. Feature: P4.
- KV3 (M) after KV1: the retention rules.
  - `includes/fn/privacy_retention.php` gains the rules (settings in `config_text`), the dry run, the pass in slices through `pg_privacy_erase()`'s actions, the skips, the `privacy_rule` audit lines and their 3-year keep in `pg_privacy_retention_run()`; the Audit trail card's sentence. No schema.
  - Tests: each skip of P8, a passive contact with a booking ahead, staff, the cart's blanked field set, the 31-day floor, the 6-month bound, the 3-year keep. Features: P6, P6b, P7, P8, P9.
- KV4 (M) after KV2: the `privacy_requests` migration step (`workflow/decisions-made-schema-36.md`), the panel list, reminders, "Prepare the answer", the 7-day sweep; the table in the catalogue and `pg_privacy_erase_tables()` (anonymise). Feature: P11.
  - Columns (message 10557): id; kind, status, source (form, account, staff) VARCHAR(16); contact_id, user_id, assigned_user_id, created_by INT UNSIGNED; email, name; message TEXT; answer MEDIUMTEXT; result VARCHAR(16).
  - verified_at, received_at, due_at, closed_at INT UNSIGNED; reminded TINYINT; keys (status, due_at), contact_id, email. The columns go in the PR body and are asked of the architect before ready.
- KV5 (S) after KV4: the My Account request, the root `privacy_request.php` (off by default, its switch in the Privacy centre), the proof link, the rate limit. Features: P10, P10b.
- KV6 (S) after KV4: `GET /privacy/inventory` and `GET /privacy/requests`, `php tools/check_api_schema.php`. Feature: P12.
- KV7 (S) close-out: every P number checked against the code with its PR; `core/privacy-centre.md` written, `core/privacy-log-retention.md`'s "Not built" line made true; this plan's three files deleted; the changelog `[NEW]` line named in its PR.

## Which steps run side by side?
- After KV1: KV2 and KV3 side by side. KV6 beside KV5.
- `includes/fn/connections_registry.php` holds a count test (`tests/connections_registry_test.php`); KV1 adds fields, not rows, so the figure stays 78.
