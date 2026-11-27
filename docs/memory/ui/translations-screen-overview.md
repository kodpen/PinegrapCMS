---
name: translations-screen-overview
description: The Translations screen (translations.php) as a whole: what it is, the redraw under way, its parts by label and which memory file holds each
---

## What is the screen?
- `translations.php` with `assets/js/translations.js` and the actions of `translations_action.php`: the site's texts in a target language, scanned from the pages, translated by an engine or by hand, reviewed. Helpers in `includes/translate/screen.php`.
- Being redrawn by `plans/translations-screen-modern.md` (TS1 to TS8) after the sheet `translations-screen-drawing.md`; what the screen did before: `translations-screen-today.md`. Nothing a user could do there is lost; no table, column or new stored value.

## Which file holds which part?
- The head (language button, tabs "Texts", "Activity", "Glossary", "Translate pending", "Scan for new texts", "More"), the rail, the filter tiles, the tool line: `translations-screen-frame.md`.
- The automation line, the dialog "Automation for <language>", the progress card: `translations-screen-automation.md`. "Activity", an update's drawer, "Try again with the failed ones": `translations-screen-activity.md`.
- The row (grid, box, state column, "Suspicious", "Details", the refusal under the box, the keys): `translations-screen-row.md`.
- The ticks, "Select all on this page", the selection bar and its dialogs ("Approve", "Translate again", "Keep as in the source and approve", "Clear"): `translations-screen-selection.md`.
- Counts that follow a save, the browser engines' wait, the glossary's refused save and duplicates, the CSV guard: `translations-screen-editor-rows.md`.
- Unsaved rows and the leave question: `translations-screen-unsaved-rows.md`. `lang` and `dir` on a text: `translations-screen-text-language.md`.
- "Why?" of a page kept out of search: `translations-screen-noindex-reason.md`. The CSV import: `translations-screen-csv-import.md`. The messages kept across a reload: `translations-screen-reload-messages.md`. Look A's classes: `look-translations-screen.md`.

## How is a new part added?
- Drawn as the sheet shows it, in the look (`screen-composition-rules.md`); its words with `lang()` and the Turkish through `tools/tr_add.php`; a JS string through the screen's `config.strings`. A save or a mark changes the list in place, never with a reload.

Related: docs/memory/ui/translations-screen-drawing.md, docs/memory/plans/translations-screen-modern.md
