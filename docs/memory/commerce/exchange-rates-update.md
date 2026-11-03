---
name: exchange-rates-update
description: Update Exchange Rates (button and scheduled job) writes one Site Log line with the count, and a success notice only when a rate was written
module: currency_rates
---

## What does a run write?

- `update_exchange_rates.php` fetches every store currency plus `ERP_FX_CURRENCIES` (`pg_currency_rates_fetch()`), overwrites `currencies.exchange_rate` and stores today's `currency_rates` row per fetched code (`erp/erp-money-and-currency.md`).
- Callers: the Update Exchange Rates button on the Currencies screen (signed-in, token-checked) and the `update_exchange_rates` scheduled job (`pg_cron_is_background_run()`, user id 0).

## Which feed addresses are called?

- Frankfurter (ECB) in one batch call: `pg_currency_rates_frankfurter_url()` builds `https://api.frankfurter.dev/v1/latest?from=…&to=…`. The old `api.frankfurter.app` host answers 301 and the outbound client follows no redirect, so until #5924 every rate fell to HexaRate, one call per code (`tests/currency_rates_feed_url_test.php`).
- HexaRate (`hexarate.paikama.co`) per code for the rest. BGN is not in `pg_currency_rates_frankfurter_codes()`: the ECB stopped it when Bulgaria adopted the euro.
- A feed that moves again shows as the Frankfurter batch failing silently; check the address with `curl -I` before suspecting the parser.

## What goes to the Site Log?

- One line per run, before the redirect, so the job logs too: `pg_exchange_rates_log_line($updated, $requested)` (`includes/fn/currency_rates.php`) gives the `pg_log_lang()` properties — "{var:1} of {var:2} exchange rates were updated", or "Exchange rates were not updated: no rate could be fetched ({var:1} requested)". Null (no line) when no currency besides the base was asked for.
- `log_activity()` names the signed-in user; the job logs with no user.
- Before #4273 the bulk change left no trace, while a single edit in `edit_currency.php` logs "Currency (…) was modified" (issue #4172).

## What does the screen show?

- The success notice "{var:1} exchange rates were updated" only when `$updated > 0`; otherwise only the errors: one when neither feed answered, and one per currency not fetched (commit 2c5da11c). Log line: PR #4273.

## Tests

- `tests/exchange_rates_log_test.php` (log line), `tests/exchange_rates_indexnow_result_test.php` (notice).
