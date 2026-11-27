---
name: translations-screen-reload-messages
description: Translations screen (translations.php): which messages survive the reload after an update, a scan, a bulk action or the automation dialog, and what the Site Log says for a language translated by hand
---

## What survives the reload after an update or a bulk action?
- Each of them toasts and reloads the screen 0.8–1.2 s later. `afterReload(message, kind)` in `translations.js` stores the message in `sessionStorage` (`pg-tr-toast`) first, and the next load shows it once with `toast()`.
- `finish(done, failed, error)` stores the update's error, else the "Finished: …" counts, as an error when a text failed and as success otherwise. Nothing pending, pending by hand, the scan, Approve all and Clear store their own message.
- Saving "Automation for <language>" stores "The automation of this language was saved."; "Stop" in an update's drawer for an update the screen does not follow stores "The update was stopped.". The cancel paths of a followed update reload with no message.
- Before PR #3087 these messages were gone before they could be read (`tests/translations_reload_message_test.php`). The selection's actions change the rows in place and reload nothing (`translations-screen-selection.md`).

## What does an update log for a language translated by hand?
- Nothing is sent, so `translations_action.php` writes `'translations were updated ({var:1}, {var:2} text(s) sent)'` only when `pg_tr_update()` created a job (`job_id` > 0); otherwise `'the texts of {var:1} were scanned ({var:2} text(s) pending)'`. "Try again with the failed ones" logs the first line when it opens an update.
- Before, the Site Log said texts were sent though nothing left the site (`tests/translations_zip_variant_wording_test.php`).

Related: docs/memory/ui/translations-screen-activity.md, docs/memory/ui/translations-screen-frame.md
