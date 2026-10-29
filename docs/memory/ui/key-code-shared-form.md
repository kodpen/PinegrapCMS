---
name: key-code-shared-form
description: The key code create and edit screens share includes/forms/key_code_form.php: the checks, the offer-code clash, the values, the generator, what stays in the screens
---

## Where is the key code form?
- `includes/forms/key_code_form.php` (gate `PG_FUNCTIONS_DIR`), loaded with `require_once` by `add_key_code.php` and `edit_key_code.php`. Done in #3010.
- Both screens still draw `includes/templates/edit_key_code.php`, chosen with `$screen` (`offers_keycodes_names_test.php` reads it there).

## What does a save share?
- `pg_key_code_form_validate($liveform, $id)` (`null` on create): Offer Code required and an offer found for it; a typed code not used by another key code (edit leaves its own id out) and not an offer's own code; a typed expiration date valid. Create may leave the code empty for the generator.
- Why the offer check: checkout applies an offer's own code first and never reads a key code with the same code (`api/api-catalogue-writes.md`). The API refused it before; the panel screens did not until #3010.
- `pg_key_code_form_values($liveform, $code)`: code, offer code, enabled, stored expiration date, notes, single use, report. The screens `array_merge()` the user id (and the edit id) after it.
- `pg_key_code_form_generate()`: ten CSPRNG characters 0-9/A-Z, checked against `key_codes` and `offers`, 50 tries, `''` after (the create screen then stops with an error). It replaced the recursive `generate_key_code()` of the create screen.
- Lookups go through `pg_key_code_form_taken($table, $code, $except_id)` (db_q); validate and generate take a stub in tests.

## What stays in the screens?
- Create: the quantity checks (1,000 limit, `limit=false` bypass), the generate loop and INSERT, the notices.
- Edit: the not-found guard (`panel_id_gate_test.php`), the row load, delete, 'Key Code is required', the UPDATE.
- Test: `tests/key_code_form_test.php`.
