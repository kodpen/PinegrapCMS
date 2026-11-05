---
name: marketplace-settings-layout
description: Marketplaces screen (marketplace_settings.php) on the wide layout: account tiles, work cards in the form column, the account card in the status column, the Settings drawer sections and form-field rows
---

## How is the screen laid out?
- The account tiles stay full width above the columns as the switcher between accounts (#6914).
- Form column (`lg:col-8 2xl:col-9`): the selected account's work cards (Matched products, the webhook, Imported orders, Returns, Questions, What did not go through).
- Status column (`lg:col-4 2xl:col-3`, sticky box at `top:3.3rem`), written after the form column: the account card with its counts, the paused and last-error alerts, Settings (opens `#mp_drawer`) and Test connection (`mp_action=test`).

## How are the Settings drawer and the Connect dialog written?
- Every label and control pair is a v6 `form-field` (`<div class="form-field mb-5">`, `form-label`, the control, a `form-text` hint inside it); the credential rows come from `mp_settings_credential_field()`, so the Connect dialog and the drawer share them.
- The drawer is four `<fieldset>` sections with a `legend` (`fs-md fw-semibold`) instead of `<hr>` rules: Connection (name, credentials, the managed note, the marketplace's help), Stock and prices (Send changes, Send stock, Send prices), Orders (Bring orders in, Accept automatically, or the "not brought in yet" line), Shipping (Cargo template, Days to prepare). Remove marketplace stays last, after one `<hr>`, and is not drawn for a managed account.
- Field ids, names and what is posted are unchanged; the refused Connect/Save rules are in `marketplace-settings-screen-a11y.md`.

Tests: `tests/marketplace_settings_layout_test.php`.
