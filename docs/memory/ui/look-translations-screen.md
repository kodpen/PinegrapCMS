---
name: look-translations-screen
description: The Translations screen (translations.php) in look A (S6c): card titles, row Save and review buttons, glossary icon buttons, dialog buttons, status pills, the style block's tokens; what stayed
---

## What moved?
- Step S6c of the plan panel-old-screens (#10547). `tools/check_look.php` counts 0 old buttons in `translations.php`; its baseline entry is gone.
- The toolbar was already the shared one (LM2, SH5): language menu fill, Update and Approve all ghost, the More menu an icon button. The draw-queue view (`draw=1`) was already look A.

## What changed in the columns?
- Card titles Pages, Other texts, Recent updates, Glossary and the texts card (page name, group or All texts): `h2.pg-title-section` in a `card-header bg-reset border-0`; a title alone is `h2.card-header.bg-reset.border-0.pg-title-section`. Their side notes (reviewed count, text count with Open the page) `pg-text-2`.
- Add a term in the Glossary head: `pg-button pg-button-quiet pg-button-sm`. A term's Edit and Remove: `pg-icon-button pg-icon-button-sm` (Remove keeps `fg-danger`).
- A row's Save: `pg-button pg-button-quiet pg-button-sm`; its review (eye) button `pg-icon-button pg-icon-button-sm`; no button group. Keep as in the source and approve, and the progress bar's Stop: quiet small.

## Which pills?
- A row's state: Reviewed teal, Machine amber, Pending gray (`pg-pill`); `translations.js` `rowState()` writes the same classes. Its engine name `pg-text-meta`.
- A job in Recent updates: done teal, failed and cancelled rose, the rest amber.
- The owner, SEO and HTML marks under a source text, and the glossary's keep as written, all languages and Aa: gray pills.

## What changed in the dialogs?
- Import CSV, Clear translations, Approve all, Keep as in the source and approve, the term dialog: Cancel `pg-button-quiet`; Import, Approve and Save the dialog's one `pg-button-fill`; Clear translations `pg-button-danger`.

## What changed in the style block?
- The source text and the translation box are `--pg-text-base` (were .9rem), inline HTML source `--pg-text-2` (was .82rem). An unsaved row's Save is drawn in `--pg-accent` (text and a 1 px inset line) instead of Bootstrap's button variables (`translations-screen-unsaved-rows.md`).
- The text column's `min-width: 0` is the `min-w-0` class.

## What stayed?
- The Artifact Canvas parts named by the artifact-canvas-rename plan (AC4): a row's picture chip (`bi-image`, `badge` button) and Edit in the Artifact Canvas / Draw the waiting pictures (`bi-brush`).
- The suspicious mark (`badge theme-danger`, an icon with a hidden text): an icon mark, not a status; the alerts (engine notes, progress, index note); the gray `small fg-secondary` notes; the language menu's code chip; the page list's 3 px and the progress bar's 4 px bar height and the reviewed row's 1em spacer: no token names them.
- The page and group lists stay Bootstrap `list-group` rows; the dialogs' fields are full width in their dialog.

Related: docs/memory/ui/look-settings-categories.md, docs/memory/ui/translations-screen-editor-rows.md
