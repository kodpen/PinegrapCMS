---
name: custom-form-rich-text
description: Rich-text text areas on designed forms: three-state _cf.wysiwyg, one editor per live page, aria-required, filtered prefill, rich seeding, open items.
---

## How does a designed field become rich text?
- Form Field panel switch "Rich-text Editor" (owned-form text areas) writes `props._cf.wysiwyg` true/false. `_pg_cf_controls()` reads it three-state: absent = `null`, and `pg_cf_rich_text_flag()` keeps the stored row's flag (a text-area row only); true/false = the canvas wins. Non-text-area fields are always 0.
- So a flag set earlier on `edit_field.php` (read-only since #1204) survives designer saves and template publish until the switch is used. A page duplicate without `_cf.wysiwyg` starts plain; undo to a tree without the key keeps the stored flag. The panel shows the stored row state (`_cfRichOn()` reads `_formFieldsCache`, refetched after each save and form move); `_cfBuildFromFields()` copies the flag with `_cfFlagOn()` (rows carry `true` or `'1'`).
- Symptom fixed: "a field-screen rich flag vanished after a designer save" (`_pg_cf_controls()` wrote `wysiwyg => false`).

## When and how does the page load the editor?
- `pg_cf_apply_field_bindings()` marks a rich textarea `data-pg-rich-text`. `get_page_content.php` then calls `pg_cf_rich_text_inject()` once the page is drawn: one `get_wysiwyg_editor_code($ids)` for every marked textarea (two forms, shared components), before the last `</body>`.
- Not in an e-mail, under `pg_seo_rendering()` (SEO pass, designer Preview) or with `UPDATE_SEARCH_INDEX`. The canvas and the DB-less template preview show a plain textarea (panel hint).
- Editor follows `page_editor_version` (CKEditor 4.20, or TinyMCE 3 for "previous"). `pg_cf_rich_text_ids()` keeps only ids matching `^[A-Za-z][A-Za-z0-9_:.-]*$` (written into JS unescaped) that no other element carries (an editor takes the first element with the id).
- Symptom fixed: "the designed form showed a plain textarea".

## What happens to `required` and pre-filled values?
- A rich textarea loses `required` and gets `aria-required="true"`: TinyMCE syncs only on submit, so `pg_cf_validation_script()` blocked it. The server's required check (`custom_form.php`) stays.
- The starting value (from any source) goes through `pg_sanitize_rich_text()` before it becomes the textarea text, because the editor parses it as HTML.

## How is a rich value stored and printed?
- The classic handler stores it as `form_data.type = 'html'`, filtered. Output keys on the field flag (legacy parity): `pg_sw_apply_tokens()` and the order-form values in `widgets_catalog.php` print `pg_defuse_php_tags(pg_sanitize_rich_text(...))`. Host element is a `div` (`wysiwyg-text-binding.md`).
- Switching the flag on for a field with answers: old plain answers lose line breaks; off: stored HTML shows as code (panel hint). Record translations re-segment as HTML.
- Template sample records (`_pg_cf_seed_given_records()`, the `blog` path's `content`): a rich text area gets `pg_cf_seed_rich_value()` (blank line = `<p>`, single newline = `<br>`, markup filtered) stored as `html`. Symptom fixed: "sample articles were one run of text".

## What is open?
- Visitor input profile (owner question, `plans/security-findings-2026-10.md`): visitor HTML keeps `class`/`style` (overlays), links get no `ugc`, external images allowed. Interim: filter record views on a staff-only status.
- Owner question: CKEditor `oembed` loads unpinned jQuery 1.x from ajax.googleapis.com on visitor pages with a rich field (no global `jQuery` there; classic forms alike). Drop it for visitors or self-host jQuery?
- CKEditor 4.20 is end-of-life; plain-text e-mail bodies show rich fields as raw HTML (#1097 area); the API writes rich fields as `standard`; the same widget twice on a page duplicates ids (neither textarea gets the editor).
- `edit_submitted_form.php` loads unfiltered `standard` rows (pre-switch answers, API writes) raw into the staff editor; `pg_sanitize_rich_text()` there is an owner question.

Related: docs/memory/designer/custom-form-render.md
