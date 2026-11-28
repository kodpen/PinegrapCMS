---
name: translations-screen-today
description: Census of the Translations screen before its redraw (2026-11-27): its views, every control, a text's states, what runs by itself, what the API reaches, and the faults the redraw answers
---

## Which views does it have? (`translations.php`, query string)
- `language`: a served target language. No `page_id` or `group`: "All texts". `page_id`: one page. `group=catalog|forms|pictures|ui`: "Products, product groups and offers", "Forms", "Images", "Cookie window". `group=pictures&draw=1`: "Draw the waiting pictures".
- `filter=all|pending|machine|reviewed|suspicious|blocking`, `q`, `p` (100 texts a page). Every move is a link and a reload.

## Which controls? (actions of `translations_action.php`)
- Language menu; "Update translations" / "Update this page" / "Update these texts" (`update`: scan, then the engine's job); the engine select, saved on its pick (`engine`); "Approve all" (`review_all`); the filter chips; "Search in the texts".
- "More": "Export CSV", "Export pending texts as CSV", "Export to Excel" (`export`), "Import CSV" (`import`), "Clear translations" (`clear`), "Languages and Translation".
- The progress line with "Stop" (`cancel`).
- A row: tick, source with "SEO", "HTML", the hash's first 8 characters; the box; the pill and the engine's name; "Save" (`save`, Ctrl+Enter too, an emptied box deletes); the eye button "Mark as reviewed" / "Mark as not reviewed" (`status`).
- Above the rows: "Select all on this page", "Keep as in the source and approve" (`accept_source`, 500 ids at most).
- A page: the index note with "Why?" (`filter=blocking`, "They come from:").
- Left cards: "Pages", "Other texts", "Recent updates" (the last 8 jobs, no control), "Glossary" ("Add a term", edit, remove: `glossary_save`, `glossary_delete`).

## Which states has a text?
- "Pending": no `translations` row. "Machine": `status = machine`. "Reviewed": `status = reviewed`. Suspicious is the flag `suspicious = 1` beside the status (`core/content-translation-result-check.md`).
- No stale state: a changed source is a new pending text; the old one is handed to the AI engines as the earlier wording (`translation_uses.prev_string_id`).
- An update: "Queued", "Sent", "Running", "Done", "Failed", "Cancelled"; its texts `waiting`, `done`, `failed`, `skipped`, each failed one with its reason in `translation_job_items.error`.

## What runs by itself?
- "Translate on save" (a language's `auto_update`; Pinegrap AI, Claude and Google only): `pg_tr_auto_update()` is called for a saved page, form record and Artifact Canvas picture; not for a product or a form (till TS6).
- `translation_job.php` every 300 s (`pg_tr_jobs_run_due()`): works Google and Pinegrap AI updates, requeues a quiet Claude one, fails one untouched for a day, draws waiting pictures. The browser engines need the open screen.
- `site_languages.fallback_engine` is stored and offered, read by no update (till TS6).

## What does the API reach? (`includes/translate/api.php`)
- Languages, strings (groups `catalog` and `forms` only), a page, write, refresh (scan only), status, the glossary read and saved; each also an MCP tool.
- The updates' routes (`/translations/jobs…`) serve Claude's application only. No route starts an update, deletes a term or reads the Images and Cookie window groups; no event tells of a finished update.

## Which faults does the redraw answer?
- At 1440 px the filter chips sit in a 239 px box for 670 px of chips: "Reviewed", "Suspicious" and "Keeping pages out of search" are out of sight.
- "Update …" scans and sends under one name; the engine select beside it changes a site setting on its pick.
- An update cannot be opened or tried again, and its failed texts' reasons are shown nowhere.
- The suspicious triangle covers the engine's name; the only action on ticked rows is "Keep as in the source and approve".
- A language with "Served" off is not offered, so it cannot be prepared before it goes live (till TS6).

Related: docs/memory/ui/look-translations-screen.md, docs/memory/ui/translations-screen-editor-rows.md
