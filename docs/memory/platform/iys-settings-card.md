---
name: iys-settings-card
description: The İYS card of Settings › Communication (includes/iys/settings.php): fields, save decision, brand check, cursor keys kept, iys_job switched on, what was not verified
---

## Where is the card?
- `#pgset-iys`, last card of the `contact` pane; markup `pg_iys_settings_fields()`, check and write `pg_iys_settings_check()` / `pg_iys_settings_save()` in `includes/iys/settings.php` (required by `contact.php` and `contact.save.php`). Same shape as the SMS card (`sms-provider-layer.md`).
- Fields: switch `iys_enabled`, API user, password (sealed, never drawn back, "Stored" placeholder), İYS code (digits), brand, environment (`live` / `test` = sandbox), mode (`iys_live` 0 = dry run, 1 = live), e-mail switch. A new account starts in dry run.

## How is the brand picked?
- The brand list `pg_iys_brands()` gave is stored in the row's `options.brands`; with a list the card draws a select, before one a digits box (empty = take it from İYS).
- On save İYS is asked (token + brand list) when the user, a typed password, the İYS code or the environment changed, when the account is switched on, while no brand is set or no list is stored. A change of mode or e-mail switch alone asks nothing.
- One brand and none chosen → taken. Several and none chosen → saved without a brand, notice "pick the brand"; the card's warning ("not complete") stays and the job does nothing. A chosen brand İYS does not list → marked, nothing written. No brand at all → İYS code marked.
- İYS refused or could not be asked → saved unchecked with a notice (the answer reader does not tell a refusal from an outage).

## What does a save write?
- `pg_iys_settings_decide()` (pure, tested in `tests/iys_test.php`): `none` (unchanged: no write, no call), `save`, `disable` (off, rest unchanged: `enabled = 0` alone), `forget` (off + empty API user: row deleted). Demo site, schema not upgraded or no encryption key marks the switch.
- `pg_iys_settings_options()` merges the card's keys into the options read just before the write, so `push_id`, `pull_after` and any later key survive a save even when the job moved them after the pane was drawn.
- A save with İYS on calls `pg_iys_job_switch_on()`: `iys_job` joins `config.job_dispatch` when the general job runs the jobs.

## What is not verified?
- No İYS account existed: the card was checked by unit tests and a stubbed render only; the token and brand calls, and the first live run against the sandbox, are still to be done once the owner gives test credentials.

Related: docs/memory/platform/iys-client.md, docs/memory/platform/sms-provider-layer.md, docs/memory/plans/sms-and-iys.md
