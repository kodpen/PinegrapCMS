---
name: translations-api-updates
description: Translation updates over the API (TS7): start, list, read with failed texts; the glossary delete; the Images and Cookie window groups; translation.update_finished
---

## Which routes does TS7 add?
- `translations.updates.list` (GET /translations/updates, `language` required, `status` all/running/failed as the Activity filter, cursor on id descending, limit 1-100, default 20) and `translations.updates.get` (GET /translations/updates/{id}: the update, `skipped` and up to 200 `failed_texts` with hash, source and the engine's reason, read with `pg_tr_activity_job_detail()`). Both `translations:read`; unlike the Claude-only `/translations/jobs`, any application holding the scope.
- `translations.updates.start` (POST /translations/updates, `language`, `page_id` or `group`): runs `pg_tr_update()` with the language's own engine, as "Translate pending" does. Answers scope, engine, pending and `update` (null when nothing was pending).
- `translations.glossary.delete` (DELETE /translations/glossary/{id}) and its twin `translations.glossary.delete_post` (POST …/delete, no MCP tool), the Glossary tab's Remove.
- The strings listing's and the start's `group` take `pictures` (the screen's Images) and `ui` (Cookie window) beside catalog and forms.

## What does a start refuse?
- 409 by `translate_api_update_refusal()` (pure): `translated_by_hand` for "By hand" and the CSV import (kind panel), `engine_in_browser` for the browser engines (they translate only while the screen is open), `engine_not_ready` with the engine's own reason.
- 409 `update_running` when an update of the same language and scope is still open (queued, sent, running); the message names its id. The screen does not refuse this; the API does so a retrying client does not send the same texts twice.
- A dry run stops after these checks, reads nothing off the pages and answers `pending` as of the last scan.

## How is the end of an update announced?
- `translation.update_finished` (scope `translations:read`), from `pg_tr_job_announce_finished()` in `includes/translate/jobs.php`, called by `pg_tr_job_close_if_complete()`, `pg_tr_job_fail()` and `pg_tr_job_cancel()` only when their UPDATE changed the row, so once per update.
- Data: id, language, scope, engine, status (done/failed/cancelled), total, translated, failed, error, `fallback_update_id` (the update opened on the fallback engine for the failed texts, null for none). No stored value was added.
- The catalogue and scope come through the module seam (`translate_webhook_events()`, `translate_webhook_event_scopes()`), fields and sample in `includes/translate/webhook_payloads.php`.

## Where is it tested?
- `tests/translate_api_updates_test.php`; the event in `webhook_event_payloads_test.php` and `webhook_event_scopes_test.php`, whose catalogues now take the translation module.

Related: docs/memory/api/translations-api-dry-run.md, docs/memory/ui/translations-screen-activity.md, docs/memory/api/api-webhook-event-payloads.md
