---
name: privacy-centre-steps
description: Plan privacy-centre, steps KV1 to KV7: the catalogue and transfers, the screen and downloads, the retention rules, the request record, the public form, API, close-out
---

## Which steps are there?
- KV1 (M) first: the catalogue and transfers, no schema.
  - New `includes/fn/privacy_inventory.php` (pure catalogue, live counts), country and data fields on `includes/fn/connections_registry.php`'s rows, `tests/privacy_inventory_test.php` red first (P5's two failures). Features: P1, P2, P3, P5.
- KV2 (S) after KV1: the Privacy centre screen where the architect places it, Excel through `includes/fn/xlsx_write.php` and PDF. Feature: P4.
- KV3 (M) after KV1: the retention rules.
  - `includes/fn/privacy_retention.php` gains the rules (settings in `config_text`), the dry run, the pass in slices through `pg_privacy_erase()`'s actions, the skips, the destruction record where the architect decides (a 2026.11.0 migration step when it is a table). Tests: each skip of P8, a passive contact with a booking ahead, the 6-month bound. Features: P6, P7, P8, P9.
- KV4 (M) after KV2: the request record and the panel list, reminders and "Prepare the answer" (migration step for `privacy_requests` once the owner says yes). Feature: P11.
- KV5 (S) after KV4: the request form per the architect's answer, the e-mail link (signed, as `core/form-double-opt-in.md`), WAF rate limit. Feature: P10.
- KV6 (S) after KV4: `GET /privacy/inventory` and `GET /privacy/requests`, `php tools/check_api_schema.php`. Feature: P12.
- KV7 (S) close-out: every P number checked against the code with its PR; `core/privacy-centre.md` written, `core/privacy-log-retention.md`'s "Not built" line made true; this plan's three files deleted; the changelog `[NEW]` line named in its PR.

## Which steps run side by side?
- After KV1: KV2 and KV3 side by side. KV6 beside KV5.
- `includes/fn/connections_registry.php` holds a count test (`tests/connections_registry_test.php`); KV1 adds fields, not rows, so the figure stays 78.
