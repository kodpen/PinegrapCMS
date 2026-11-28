---
name: content-translation-fallback-engine
description: A language's fallback engine (site_languages.fallback_engine) takes the texts an update failed on when it ends: which engines, which texts, once only (TS6)
module: translate
---

## When does the fallback engine work?
- When an update ends: `pg_tr_job_close_if_complete()` with failed texts, or `pg_tr_job_fail()` (engine not ready, a day untouched, Claude's report of a failure). Only the call that closes the job (one row changed) hands on, so two runs ending together open one update.
- `pg_tr_job_fallback()` opens a new update of the same language, scope and starter through `pg_tr_job_open()`: Claude is told, a server engine's update waits for the screen or the 5-minute job.

## Which engine takes them?
- `pg_tr_fallback_engine_for()` (pure): the language's fallback when set, not the engine the update ran on, ready, and of kind `server` (Google, Pinegrap AI) or `queue` (Claude).
- A browser engine needs an open screen and "By hand" sends nothing: their failed texts wait for "Try again with the failed ones" and its caret "Try again with <fallback>" on Activity.
- An update on the fallback engine itself has none, so a fallback never falls back again.

## Which texts?
- The update's `failed` items; for an update failed as a whole also its `skipped` ones (the texts it never reached).
- Only those still without a translation (`pg_tr_activity_retry_ids()` over `pg_tr_screen_statuses()`): a text translated meanwhile, or skipped because it was, is not sent.

## What did it do before?
- Until TS6 (PR #12228) the column was stored and offered on the settings card and in the dialog "Automation for <language>", and read by no update. The dialog's help now says "Takes the texts the engine could not translate when an update ends. A browser engine takes them only from "Try again with the failed ones"."

## Where is it tested?
- `tests/translations_settings_promise_test.php`.

Related: docs/memory/ui/translations-screen-activity.md, docs/memory/ui/translations-screen-automation.md, docs/memory/core/content-translation-jobs-duplicates.md
