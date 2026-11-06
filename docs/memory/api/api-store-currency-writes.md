---
name: api-store-currency-writes
description: Currency writes and the rate refresh (store:write) through includes/fn/store_currencies.php, shared with the screens; symbol as text, rate and base rules, currency.* events
module: store_currencies
---

## Where is a currency written?
- `includes/fn/store_currencies.php`: `pg_store_currency_save()`, `pg_store_currency_delete()`, `pg_store_currency_rates_refresh()`. `add_currency.php`, `edit_currency.php`, `update_exchange_rates.php` (the button and the scheduled job) and the API handlers call them. A save returns 0 and a delete false on a failed query; the screens answer `output_error('Query failed.')`, the API 500.
- A currency saved as base takes `base` off every other currency (this replaced `pg_currency_form_clear_other_bases()`).
- The refresh returns `wanted`, `reached`, `failed`, `updated`; `update_exchange_rates.php` turns them into the screen's marks, notice, Site Log line and `pg_cron_ran()`. `pg_store_currency_rates_wanted()` (pure) picks the codes: store currencies plus `ERP_FX_CURRENCIES`, no base, no repeats.

## Which events are announced?
- `currency.created`, `currency.updated` (id, code, exchange_rate, base) from a save; `currency.deleted` (id, code) from a delete; the refresh announces `currency.updated` only for a store currency whose five-decimal rate moved. The screens, the job and the API alike.

## What does the API take?
- Routes (`store.currencies.*`): GET one (`orders:read`); create (201), update (POST, PATCH also accepted), DELETE and its `POST …/delete` twin, `POST /store/currencies/rates` (`store:write`, all X-Dry-Run); handlers in `includes/api/resources/store_currencies.php`. A currency answers `StoreCurrency`, as `GET /store/currencies`.
- `symbol` is plain text both ways: escaped with `htmlspecialchars()` into the stored HTML the pages print, read back by `api_currency_symbol_text()`; an entity a client sends stays text. The stored form must fit `varchar(10)` (422).
- `exchange_rate` is read as a decimal, not through the screen's masked-amount parser (`pg_normalize_amount()` reads `.` as a thousands mark in Turkish). Above zero after rounding to five decimals, at most 99999.99999; the base is always 1.
- The screen's refusals: blank name, code not three letters (422), the stored base switched off (422), a code another currency has (409 `code_taken`), deleting the base (409 `base_currency`).
- An update writes `exchange_rate` and `base` only when they change: `api_store_currency_kept()` (pure) names the unchanged ones and `pg_store_currency_save()`'s optional `$kept` leaves them out of the UPDATE (`pg_store_currency_update_sets()`, pure); the other bases are cleared only when `base` is written, and the event carries the stored rate and base. Before, a rename wrote back the rate and base read at the start, so a rate refresh or another currency made the base landing in between was undone (a stale base switch also took the base off the new one). The screens and the list quick edit still post every column.
- The rates route answers `requested`, `updated`, `failed`, `reached`, logs the Site Log line under the owner and counts as a run of the scheduled job.

## Tested?
- `tests/api_store_currencies_test.php`: routes and column limits, partial update, symbol round trip, rate rules, the screen checks, the wanted codes, events, check-stop-write order, screens with no currency SQL of their own. Not run against a database.
