---
name: custom-form-options-and-rules
description: custom_form options belong to the element (no _choices array), page_type is not the form marker (pg_form_page_sql), validation, confirmation, email body, CAPTCHA and migration rules.
---

## Where do select / radio / checkbox options live?
- `<select>`, radio and checkbox carry their own option list; the "Seçenekler" panel opens everywhere. `<select>`: `<option>` children. Radio/checkbox: sibling `.form-check` blocks (`_sdCheckBlocks`).
- **Never write a parallel `_choices` array.** Markup is the store.
- **A group is cloned, not recreated**; ids are `stem`, `stem_2`, `stem_3`; the clone copies the template's `name`.
- **No duplicate key in an object literal.** Read text with `_sdTextOf()`.

## How is a page with a form recognised?
- A visual page stays `page_type='standard'`; the `custom_form_pages` row + being a visual design says it has a form. Every query that counts/lists forms uses `pg_form_page_sql($alias)`. Sitemap / search index / legacy render branches are type semantics and are not touched. When writing a new form list, never write `page_type = 'custom form'`.

## What other rules apply?
- **Validation in two places:** `pattern` in the browser, `custom_form.php` with `/^(?:…)$/u` on the server; a broken expression is ignored. Form is `novalidate` + `needs-validation`; `pg_cf_validation_script()` once per page.
- **Next page** belongs to the form record (`confirmation_type='page'` + `confirmation_page_id`), not a widget setting; a nonexistent page falls back to the message.
- **An empty email body means an empty email.** `pg_cf_default_email_body()` builds it from the fields; the moment the operator changes one letter the body is theirs.
- **Do not redraw the form after submit** (`?<page_id>_confirmation=true`).
- **Errors come from liveform**, narrowed to this form (`_pg_inject_messages_node($tree, (string)$page_id)`).
- **CAPTCHA is a section binding**; if it returns empty the container drops too.
- `pg_rendered_page()` is registered **early** in `get_page.php`.
- `pg_cf_page_form_ready()` probes three tables at once; on a non-upgraded install the feature silently disappears.
- The legacy field screen (`edit_field.php`) still works but **the canvas is the record**: an `rss_field` changed there is overwritten on the next design save by the control's `_cf`.
- Old trees migrate on load (`_cfMigrateWidgetTree`: `_bindings.form_field` -> `name`, `__submit_label` binding -> button text) and are written in the new shape on first save.

Related: docs/memory/designer/custom-form-model.md
