---
name: ai-write-marks
description: Mark on texts written with AI (products.ai_fields, page.ai_fields, 11.0.23): what is stored, the fingerprint, when a field gets or loses it (a hand edit removes it), the marker, pg:ai-write-used
---

## What is stored?
- `products.ai_fields`, `page.ai_fields` (TEXT NULL, `upgrade_2026_11_0_ai_fields()`, 11.0.23, PR #811): a JSON object of the columns whose text is an accepted "Write with AI" draft, each with the fingerprint of that text, `{"title":"0123456789abcdef"}`; NULL is none, never `{}`.
- Fingerprint (`pg_ai_fields_fingerprint()`): 16 hex digits of the SHA-1 of the value, markup included, runs of ASCII white space as one space, ends trimmed (a CRLF post is the same text); '' for a field without text.
- Columns, one per button (`pg_ai_fields_columns()`): product `short_description`, `full_description`, `title`, `meta_description`; page `page_title`, `page_meta_description`; the test fails when a target has no column or a column no button.
- A mark holds only while its text matches (`pg_ai_fields_marked()`): a text changed any other way (API, import, Workspace, SQL) is not shown as marked and not kept by the next save, with no code on those paths.

## When does a field get the mark?
- "Use this text": `assets/js/pg_ai_write.js` sends `pg:ai-write-used` from the field once the draft is in (CKEditor: `setData()` callback; TinyMCE: docs/memory/ui/ai-write-tinymce.md). `assets/js/pg_ai_fields.js` (only the two screens) lists the column in the hidden `ai_fields` input, shows the marker, remembers the text. The save stores each listed column with the fingerprint of the text it writes (`pg_ai_fields_after_save()`). Form records ignore the event.

## When does it lose it?
- A hand edit (rule since 2026-10-20): the script compares the text with the remembered one on `input`/`change`, the editor's events and the form's `submit`; a difference takes the column off the list and hides the marker, the same text back (an undo) puts it on again. The mark says the text is the draft as used; an edited text is the person's.
- The marker's x ("Remove the AI mark"), until another draft is used. A field saved empty.
- A save without a list (input missing, a request made by hand) keeps only the stored marks whose text it leaves unchanged. A posted list is trusted; `[]` clears all.

## What does the screen show?
- Product screen (create and edit, `product_builder.php`), page properties (`edit_page.php`): after the label a badge, stars icon and `lang('AI')` (Turkish "Yapay zekâ", the designer's revision label key; no second "AI" key), tooltip "Written with AI" (also visually hidden), the x with an aria-label. Every field gets a marker, `d-none` until marked. Shown even to a person who may not ask for drafts; in the Recycle Bin without the x.

## And the visual designer?
- Page settings: docs/memory/ui/ai-write-marks-designer.md.

## And a variant set?
- The create screen copies the texts to every variant. A variant keeps the short description's mark only while its own contains the typed one (the matrix default "<text> <options>"; a template drops it): `pg_ai_fields_for_variant()`. `product_groups` has no column.

## Column missing?
- A database that recorded 2026.11.0 before the step: `pg_ai_fields_ready()` (`pg_schema_has()`) says no, the screens draw and write nothing; rerun the version (docs/memory/release/rerun-upgrade-steps.md). An UPDATE naming a missing column fails whole.

## And a copy?
- `duplicate_style.php` takes `page.ai_fields` with the texts; the product and page copies write it with `pg_ai_fields_copy()`: the marks of the texts copied unchanged. A text the copy changes (letters replaced, find and replace) loses it (#2123).

## What does not mark yet?
- The API, imports, Workspace proposals; the token `__ai_generated`. See docs/memory/plans/ai-write-buttons.md.

## How was it checked?
- Sandbox (MariaDB 10.11): the step twice; saves over HTTP (marked, kept, hand edit with or without the list, changed elsewhere, emptied, x); Playwright, stand-in Prompt API (typing, editor, submit, x).
