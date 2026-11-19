---
name: look-settings-mail-sending
description: Site Settings › E-mail sending in look A (S5e): the purpose line and Mailboxes link, settings rows with pg-field-label, tiles, the DKIM field, the Sending domain card's list rows and pills
---

## What does the category look like?
- Since S5e (plan panel-old-screens), after S5d's Mailboxes.
- The Sending service card opens with one `pg-card-sub` line: the category is only about sending, then "For incoming e-mail: Mailboxes", a link to `pg_settings_url('mailboxes')` (the mirror of the Mailboxes card's "For sending" line, `ui/look-settings-mailboxes.md`). No card or button calls a sending setting a mailbox or an inbox.
- Fields are Site Settings rows (`look-settings-screens.md`): each label + control in a `.row > .col-12` cell, label `form-label pg-field-label`, the control `.form-control` (the settings row's 32 px field; `pg-input` would leave the row grid). The "Send through" selects and the Connect line sit in the rows too, no `my-3` blocks. Old `md:col-*` widths went: the row grid draws every cell full width.
- Switch rows (`.form-field > .switch`): Campaign's job switch and MailChimp Sync, each in a `.row`, each with a `pg_settings_tile()` (`megaphone`, `envelope-paper`, tone `team`); the rows in their folds carry none, as Live Chat.
- Outside the row grid: the Plain Text Footer and the DKIM record are `textarea.pg-input`, labels `pg-field-label`; the DKIM selector and domain are one `div.pg-input` holding both inputs and `._domainkey.` (no `input-group`), `col-12 pg-field-span-6`; MailChimp's Remove the stored key is `input.pg-checkbox`.
- Sending domain card: the settings card title, the intro as `pg-card-sub`, each record (SPF, DKIM, DMARC) and Bounces and complaints a `pg-list-row` with a `pg-icon-tile pg-tone-team`; `pg_mail_health_badge()` draws a `pg-pill` (Good teal, Check amber, Missing or wrong / Over the limit rose, Could not check gray). The record to publish is `pg-input pg-input-sm` + Copy.
- The transport warning stays an alert (no recipe draws one). Test: `tests/smtp_settings_layout_test.php`.

## Which buttons?
- Since the move from SMTP Settings (#10404) the buttons are `pg-button pg-button-quiet pg-button-sm` with Bootstrap icons, the Connect buttons and the Sending domain card's Copy too (`ui/look-new-parts.md`); the copy listener binds once per page (`window.pgMailCopyRecordBound`).
- Since S5e their icons have no `me-1` and they sit in `d-flex gap-2` lines.

Related: docs/memory/ui/settings-mail-sending.md, docs/memory/ui/look-settings-mailboxes.md
