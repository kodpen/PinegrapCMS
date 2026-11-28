---
name: translations-screen-overview
description: The Translations screen (translations.php) as a whole: what it is, the rules of its 2026-11 redraw, its parts by label and which memory file holds each
---

## What is the screen?
- `translations.php` with `assets/js/translations.js` and the actions of `translations_action.php`: the site's texts in a target language, scanned from the pages, translated by an engine or by hand, reviewed. Helpers in `includes/translate/screen.php`.
- Redrawn in eight steps (TS1 to TS8, PRs up to #12236, 2026-11) after the sheet `translations-screen-drawing.md`, which wins over any description; what the screen did before: `translations-screen-today.md`. Nothing a user could do there was lost.
- Owner (2026-11-27): modern, redrawn from the ground up, fit for automation (agent channel message 15186).

## Which rules bind it?
- Architect (2026-11-27): no table, no column, no new stored value for this screen; a label of the old screen keeps its words. A change that needs a stored value goes to the architect.
- Architect (2026-11-27): no engine select that saves on its pick: the dialog "Automation for <language>" saves the engine, the fallback engine and "Translate on save" with its button, into the columns Site Settings › Languages and Translation writes; that card stays.
- Architect (2026-11-27): "Scan for new texts" only scans; "Translate pending" scans and sends. A save, a mark and a bulk action change the list in place.
- The visual editor's "Translate" section uses the same actions: none is renamed. A reviewed text is replaced only with the dialog's tick (`pg_tr_reviewed_kept()`).
- Owner decisions kept: "Cookie window" is translated here only (`decisions-made-product.md`), the group "Images" (`decisions-made-product-70.md`), a page's search verdict (`decisions-made-product-38.md`), Lorem ipsum never listed.
- Left out, the owner's to ask for: machine translations approved by themselves, a translator's role. The picture draw queue and the Languages and Translation card were not redrawn.

## Which file holds which part?
- The head (language button, tabs "Texts", "Activity", "Glossary", "Translate pending", "Scan for new texts", "More"), the rail, the filter tiles, the tool line: `translations-screen-frame.md`.
- The automation line, the dialog "Automation for <language>", the progress card: `translations-screen-automation.md`. "Activity", an update's drawer, "Try again with the failed ones": `translations-screen-activity.md`.
- The row (grid, box, state column, "Suspicious", "Details", the refusal under the box, the keys): `translations-screen-row.md`.
- The ticks, "Select all on this page", the selection bar and its dialogs ("Approve", "Translate again", "Keep as in the source and approve", "Clear"): `translations-screen-selection.md`.
- "Glossary": the table, its search, the remove form: `translations-screen-glossary.md`.
- Counts that follow a save, the browser engines' wait, the glossary's refused save and duplicates, the CSV guard: `translations-screen-editor-rows.md`.
- Unsaved rows and the leave question: `translations-screen-unsaved-rows.md`. `lang` and `dir` on a text: `translations-screen-text-language.md`.
- "Why?" of a page kept out of search: `translations-screen-noindex-reason.md`. The CSV import: `translations-screen-csv-import.md`. The messages kept across a reload: `translations-screen-reload-messages.md`. Look A's classes: `look-translations-screen.md`.

## How is a new part added?
- Drawn as the sheet shows it, in the look (`screen-composition-rules.md`); its words with `lang()` and the Turkish through `tools/tr_add.php`; a JS string through the screen's `config.strings`. A save or a mark changes the list in place, never with a reload.

Related: docs/memory/ui/translations-screen-drawing.md, docs/memory/ui/translations-screen-today.md
