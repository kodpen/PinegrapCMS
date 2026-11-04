---
name: smtp-settings-screen-layout
description: SMTP Settings on the wide edit layout: which cards sit in the form column, the Sending domain card in the status column, why it is not pinned
---

## How is `smtp_settings.php` laid out?
- The product_builder pattern (`ui/edit-screen-wide-layout.md`, #6827). Form column: Sending service, then System and Campaign side by side from `xl` (stacked below; at `lg` a half of the form column was too narrow for host and port), then DKIM TXT Record.
- Status column: the read-only Sending domain card (SPF, DKIM, DMARC, bounces and complaints). Not pinned: with the TXT records to publish it is taller than the window.
- Save bar: its own `col-12 order-3 position-sticky` column; the hidden first submit button that makes Return save stays first in the form (`smtp-settings-keyboards.md`).

## Why does `pg_mail_domain_health_card()` take a second argument?
- `$wrap_column` (default `true`) wraps the card in `.col-12` for a `.row`; the status column passes `false` and gets the card alone, so it is not indented by a column inside a column. `id="mail_domain_card"` stays the target of the ops alert link (`ops_alerts.php`). Test: `tests/smtp_settings_layout_test.php`.
