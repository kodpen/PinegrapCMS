---
name: translations-screen-activity
description: Translations screen "Activity" tab (TS4): the table of a language's updates, the filter, the drawer with failed texts and reasons, "Try again with the failed ones" with the fallback, "Stop"
module: translate
---

## What does the table list?
- Every update (`translation_jobs`) of the language, 20 a page (`ap`), newest first: Status pill (Queued, Sent, Running amber; Done teal; Failed, Cancelled rose), What, Engine, Progress ("{n} of {m}", a bar while open, "{n} failed" rose), By, Started, Took (`pg_tr_activity_took()`: from the hand-over, or the opening, to the end).
- The filter above (`pg-seg` links, `af`): All, Running (queued, sent, running), Failed (failed, or failed texts left). The tab's count stays the open updates plus the finished ones with failed texts.
- By: the starter's contact name, else the username (`pg_tr_activity_user_names()`, `pg_user_display_name()`); "—" for 0.
- None: "No update has been run for this language yet." (filtered: "No update matches this filter.").

## What does an update cover?
- `pg_tr_activity_scope_label()`: `all` "All pages"; `group:<key>` the group's label; `page:<id>` the page's name; `records:<id>` Forms; `file:<id>` Images; a page gone or a scope not known "One page". Before #851 (PR #1028) every scope but `all` read "One page".

## What does the drawer show?
- A row (or its name button) opens `#pg_tr_job_drawer` (34rem): 'Update of "<scope>"' and the status pill; Translated, Failed, Skipped figures; a bar while open; Engine, Started (date · person), Finished or "Not yet"; the update's own `error` as text (before PR #3087 it was a hover title only); "Failed texts".
- The `activity_job` action reads it (`pg_tr_activity_job_detail()`): up to 200 failed texts, each with its source (HTML as words, two lines) and `translation_job_items.error` ("The engine gave no reason." when empty), and "Open the text", which searches the Texts tab for the hash's first 8 characters (the search also matches a hash prefix of 8 or more hex characters).

## What does the foot do?
- Open update: "Stop" (the screen's own loop when it follows that update, else `cancel` and a reload).
- Ended with failed texts: "Try again with the failed ones" (`retry`, `pg_tr_activity_retry()`): the failed texts still pending (`pg_tr_activity_retry_ids()`; one translated since is left) go to a new update of the same engine under the same scope via `pg_tr_screen_translate_again()`, followed like "Translate pending". Its caret "Try again with <fallback>" is drawn when the language has a fallback engine and hidden when the update ran on it.
- A browser engine's translator is made in the click; a retry keeps its engine (`prepareJob(engine, true)`), so the browser AI is not swapped for the Translator API there.

## Where is it tested?
- `tests/translations_activity_test.php`.

Related: docs/memory/ui/translations-screen-automation.md, docs/memory/ui/translations-screen-reload-messages.md, docs/memory/ui/translations-screen-drawing.md
