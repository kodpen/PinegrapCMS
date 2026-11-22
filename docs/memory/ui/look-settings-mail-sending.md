---
name: look-settings-mail-sending
description: Site Settings › E-mail sending in look A (S5e, EM2, EM3): settings rows with pg-field-label, the ways sheets' drawer fields, tiles, the DKIM field in Sending domain's DKIM row, the Sending domain card's list rows and pills
---

## What does the category look like?
- Since S5e (plan panel-old-screens), after S5d's Mailboxes.
- Since EM2 the tab is folded rows (`settings-mail-sending-rows.md`); the purpose line of the old Sending service card went with it (the category's head says what it is). How e-mail leaves is one `pg-card-inner` of `pg-value-row`s, the one frame an opened row may hold (`screen-composition-nesting.md`).
- The two ways sheets (system e-mail, campaigns) are drawers: choice cards (`.pg-start.pg-start-choice`), then the chosen way's fields in the drawer recipe: `pg-input` (36 px, the drawer's field), `pg-field-label`, `pg-field-help`, `row g-5`; hostname and port side by side.
- Fields are Site Settings rows (`look-settings-screens.md`): each label + control in a `.row > .col-12` cell, label `form-label pg-field-label`, the control `.form-control` (the settings row's 32 px field; `pg-input` would leave the row grid). No `my-3` blocks. Old `md:col-*` widths went: the row grid draws every cell full width.
- Switch rows (`.form-field > .switch`): Campaign's job switch and MailChimp Sync, each in a `.row`, each with a `pg_settings_tile()` (`megaphone`, `envelope-paper`, tone `team`); the rows in their folds carry none, as Live Chat.
- Outside the row grid: the Plain Text Footer is `textarea.pg-input`, label `pg-field-label`; MailChimp's Remove the stored key is `input.pg-checkbox`.
- DKIM row (EM3): after the record's lines, one group (`role="group"`) with its visible `pg-field-label` "Selector and domain" (scan finding of 2026-11-21), the selector and domain as one `div.pg-input` holding both inputs and `._domainkey.` (no `input-group`), a help line, then "Save and generate a DKIM key" quiet. The site's record to publish is the row's Copy field, not a textarea.
- Sending domain card: the settings card title, the intro as `pg-card-sub`, each record (SPF, DKIM, DMARC) and Bounces and complaints a `pg-list-row` with a `pg-icon-tile pg-tone-team`; `pg_mail_health_badge()` draws a `pg-pill` (Good teal, Check amber, Missing or wrong / Over the limit rose, Could not check gray). The record to publish is `pg-input pg-input-sm` + Copy.
- The transport warning stays an alert (no recipe draws one). Test: `tests/smtp_settings_layout_test.php`.

## Which buttons?
- Since the move from SMTP Settings (#10404) the buttons are `pg-button pg-button-quiet pg-button-sm` with Bootstrap icons, the Connect buttons and the Sending domain card's Copy too (`ui/look-new-parts.md`); the copy listener binds once per page (`window.pgMailCopyRecordBound`).
- Since S5e their icons have no `me-1` and they sit in `d-flex gap-2` lines.

Related: docs/memory/ui/settings-mail-sending.md, docs/memory/ui/look-settings-mailboxes.md
