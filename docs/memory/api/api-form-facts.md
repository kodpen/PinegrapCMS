---
name: api-form-facts
description: What GET /forms/{id} and /forms/{id}/fields tell a client before it writes a record: framework, is_collection, record_url_pattern; rich_text, html_allowed and max_length on a rich text area
---

## What does GET /forms/{id} add beside the fields?
- `api_form_facts()` (`includes/api/resources/forms.php`), single-form endpoint only (the list runs none of its queries):
  - `framework`: the page's design (`page.page_style`, else the folder's through `get_style()`), its `style.style_framework` read through `pg_design_framework_key()`: `bootstrap5` or `custom` (`designer/design-framework.md`).
  - `is_collection`: `custom_form_pages.collection` when `pg_coll_ready()`, else false.
  - `record_url_pattern`: the first public page with a record view of the form (`pg_ap_record_page()`, the Fediverse outbox's rule), built by `api_form_record_url_pattern()` as `pg_sw_record_url()` builds an address: `/<page>/{address_name}` when the form has pretty addresses, `/<page>?r={reference_code}` otherwise; a record without an address name is read at `?r=` even then. Null when no public page shows the records.

## What does a FormField say about rich text?
- `rich_text`: a text area with `form_fields.wysiwyg` on (`api_form_field_is_rich_text()`); its answer is stored with the form data type `html`. The field `type` stays `text area`.
- `html_allowed` (object `FormHtmlAllowed`, null elsewhere): `api_form_html_allowed($host)` from `pg_rich_text_allow_list()` (`includes/fn/editor.php`), the lists `pg_sanitize_rich_text()` itself reads, under the visitor profile: no `class`, `rel` left out (the site writes its own), `style_properties` the visitor list, `image_hosts` the site's host (relative images pass too), `url_schemes`, `dropped_tags`.
- `max_length` (null elsewhere): `api_form_html_max_length()`, 200000 characters, the API's ceiling for long text bodies; the column is a longtext. The operator's `maxlength` is not applied to rich text.
- Choice fields keep `options` as before.

## What keeps the list and the sanitiser together?
- The sanitiser reads its tags, attributes, per-tag attributes, visitor style properties and schemes from `pg_rich_text_allow_list()`; a change there changes the API answer too. `tests/api_forms_test.php` checks that every listed tag and attribute survives `pg_sanitize_rich_text()` and an unlisted one does not.
