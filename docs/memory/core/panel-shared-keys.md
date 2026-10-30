---
name: panel-shared-keys
description: Short keys shared by many screens that were read in the wrong sense (SEO, Created, Given, the uptime note) and the separate keys used instead
---

## Which shared keys were wrong, and how were they fixed (#3608)?
- `SEO` read "Ar.Mot.Opt."; it is "SEO" in Turkish too. Value only: every SEO label uses the key.
- `Given` is "Verildi", a state: the cheque status (`includes/erp/cheques.php`) and the privacy export's consent (`privacy_export.php`). The cheque direction on `erp_cheques.php` (row label and filter, beside `Received` "Alınan") uses its own key `Given out` = "Verilen".
- `Created` is "Oluşturulma", a column header used by about 30 lists. The audit trail's action label (`pg_audit_view_labels()`, `includes/fn/audit_view.php`) needs "Oluşturuldu" under a key of its own; still open while draft #3682 held that file.
- The uptime note on the general settings ("Uptime monitors can poll this address…") read "Erişilebilirlik izleyicileri", the a11y report's word. It is "Çalışma süresi (uptime) izleyicileri …", colon kept.

## What is the rule?
- A short key keeps the sense most screens use; a screen that needs another sense gets a longer key ([[translation-rules]]). Values and the source are pinned in `tests/panel_shared_keys_test.php`.
