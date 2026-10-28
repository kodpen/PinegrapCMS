---
name: currency-shared-form
description: The currency create and edit screens share includes/forms/currency_form.php: what it holds, what stays in the screens
---

## Where is the currency form?
- `includes/forms/currency_form.php` (gate `PG_FUNCTIONS_DIR`). `add_currency.php` and `edit_currency.php` stay at the root as thin wrappers and `require_once` it.
- `pg_currency_form_card($liveform)` prints the Options card: name, code, symbol, exchange rate (money mask, five decimals), base switch.
- Name and code carry `required` on both screens; the edit screen had it on neither before, the server-side check was always on both.

## What does a save share?
- `pg_currency_form_code_in_use($code, $id)` (`null` on create, the posted id on edit) through `db_q()`; the screen marks the error and redirects.
- `…_exchange_rate($posted, $base)`: the masked rate normalised (`pg_normalize_amount()`) and written with five decimals, never in exponent form; `1` for the base currency.
- `…_clear_other_bases($id)` takes the base flag from every other currency when the saved one is the base (now a `db_q()` placeholder on both screens).

## What stays in the screens?
- Create: the required checks, the INSERT, the log line and notice.
- Edit: the not-found guard, the record load, delete, the required checks, the UPDATE.
- Tests: `tests/currency_form_test.php`; `db_q_commerce_tail_test.php` and `db_q_zones_currencies_jobs_test.php` count the `db_q()` calls of the screens and the form.
