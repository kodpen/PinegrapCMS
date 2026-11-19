---
name: translations-screen-unsaved-rows
description: Translations screen (translations.js): a row with typed, unsaved text, its Save highlight, the review (eye) button on it and the leave question
---

## When is a row unsaved?
Typing in a row's box (`.pg-tr-target`) adds `pg-tr-dirty` to the row, which highlights its Save button. `rowState()` takes it away after a save or a status change answered. Nothing else marks a row.

## What does the review (eye) button do on an unsaved row?
It saves the typed text with the new status through `saveRow(row, button, status)`: "Mark as reviewed" stores it reviewed, "Mark as not reviewed" stores it as a machine draft (engine `manual`). On a row without typed text it only changes the status (`status` action).
Before, the button marked the stored text and cleared the unsaved mark: the box kept the typed words, the next load brought the old text back, now reviewed.

## Does the screen ask before it is left?
Yes, while any row is unsaved: a `beforeunload` listener in `translations.js` (the browser's own question, no `lang()` key; the panel rule is `unsaved-changes-guard.md`). It covers the filter chips, page links, the language switch, closing the tab and the screen's own reloads after a job or a dialog post.

## Where is it tested?
`tests/translations_screen_faults_test.php` (source checks; the clicks need a browser).
