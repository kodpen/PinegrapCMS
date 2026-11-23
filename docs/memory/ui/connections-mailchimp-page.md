---
name: connections-mailchimp-page
description: Mailchimp's page on Connections (EM6): sync switch, key, list, store and limits set there under E-mail › Sending's gate; Campaign mail shows the connection; old anchors open the page
module: mailchimp_settings
---

## Where are Mailchimp's key, list and sync set?
- On `connections.php?service=mailchimp` since EM6, no longer in Site Settings › E-mail › Sending (architect 2026-11-21, plan mail-settings-one C6: the other services' keys are on Connections). Before: the MailChimp row of the Sending tab, earlier a Communication card.
- "Connection details" (`connections_mailchimp_page()`, fields `pg_mailchimp_settings_fields()`): MailChimp Sync switch, API Key (a password box never drawn back; "Remove the stored key"), List ID, Store ID, Historical Sync, Limit Sync, Automation, "Save". Field names unchanged.
- Status has "Disconnect" (`mailchimp_remove`) while a key is stored: the sync goes off and the key is removed; list and store stay (`pg_mailchimp_settings_forget()`).

## What does a save check?
- `pg_mailchimp_settings_decide()` (pure, tested): with the sync on, a key with its data center, a list and a store are required; an empty key box keeps the stored key (`core/mailchimp-key-kept.md`).
- `pg_mailchimp_settings_check()` then asks Mailchimp: the key (`/ping`), the list, the store; a missing store is created with the site's name; automation sets the store's `is_syncing`. A refusal is marked on its field with the reason, key masked (`pg_mail_settings_mailchimp_reason()`), and nothing is written. As before, only now on its own page.
- `pg_mailchimp_settings_save()` writes the seven `mailchimp*` columns of `config`. The Sending tab's save no longer reads or writes any of them, so it cannot blank them.

## Who may change it?
- Who may open the Sending tab (`connections_mail_sending_may_manage()`, `pg_settings_may_open('mail_sending')`): card `details` of `pg_connections_screen_page_cards()` with `$can['mail_sending']`; the write checks the same. Nobody gains or loses a right.

## What does the Sending tab show?
- Campaign mail ends with "Mailchimp connection" (`#pgsub-mailchimp-connection`): Connected when the sync is on and a key is stored, "Manage in Connections" / "Connect in Connections". The MailChimp row went (`pg_mail_sending_fold_order()`: leaves, domain, campaign, test).

## Where do old links go?
- `pg_settings_anchors_to_connections()` (`includes/settings/registry.php`): `pgset-mailchimp`, `mailchimp_row`, `mailchimp_key` → service `mailchimp`. `modal.php` prints it as `data-pgaway`; `backend.src.js` opens the page from `open()`, `fromHash()` and an old `smtp_settings.php#…` link. They are no longer in `pg_settings_legacy_anchors()`.
- Registry row: `place` `connections.php`, `found_at` `#settings/email/pgset-mail-campaign`; no service was added (`pg_connections_count()` 84).

Tests: `tests/connections_mailchimp_test.php`, `tests/smtp_settings_layout_test.php`, `tests/mailchimp_key_test.php`.
