---
name: settings-code-field-texts
description: A Site Settings code field is attached by the card that prints it, never in includes/settings/prep.php, so every category's editors get their translated texts
---

## Where is a settings code field attached?
- In the card that prints it, in its category module (`includes/settings/<key>.php`): `pg_code_editor_attach()` after the textarea, or added to the card's markup, as the Structured Data card (`seo.php`) does for the site-wide JSON-LD field (`custom_jsonld`) that `prep.php` builds.
- Never in `includes/settings/prep.php`: it runs before the cards of every category but the Workspace ones, and what it builds is printed only by the card that uses it.

## Why does it matter?
- The first `pg_code_editor_attach()` of a request also prints `pg_code_editor_includes()`: `window.pgCodeEditorConfig` with the engine's translated texts (search panel, line prompt, fold markers) and the labels, once per request. The panel header names only the two addresses.
- While `prep.php` attached the JSON-LD field, those texts went into markup only the SEO category prints: the code fields of Maintenance (scheduled jobs), Appearance (custom CSS) and Features (social networking code) opened with English texts in the settings dialog and on their full-page screens; SEO was right. Fixed in #11057.
- An editor reads the texts when it is built, after the pane's scripts have run (the dialog inserts `html`, `modals` and `scripts` in one go), so they may come anywhere in the pane's markup.
- `tests/settings_code_editor_texts_test.php` checks that `prep.php` draws no code field and that the Structured Data card attaches the JSON-LD field.

Related: docs/memory/ui/panel-code-fields.md, docs/memory/core/code-editor-facade-and-bundle.md
