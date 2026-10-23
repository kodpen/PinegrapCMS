---
name: custom-form-translate-switch
description: Per-field "Include in translation" switch of custom forms (form_fields.translate_values, step 11.0.26): three-state _cf.translate, extraction and render skip, legacy screens, stale store and pages
---

## What does the switch do?
- `form_fields.translate_values` (TINYINT, default 1; migration step 11.0.26, PR #1347). 0 on a text box or text area: the answers visitors give in that field are not listed in Translations, not sent to an engine and not looked up when a form list or item view widget that includes its records draws them, so they never count toward a page's noindex under the "reviewed" policy.
- Field labels, help texts, validation messages and option labels stay translatable; the switch covers record values only.
- Why: `pg_tr_translatable()` guesses data from the value (`core/translation-translatable-text.md`, PR #1171); a field that always holds icon names, codes or JSON is better marked once. Owner decision (2026-10-23): a per-field switch; existing and new fields stay included.

## Where is it set?
- Designer: Form Field panel switch "Include in translation" (`sd-cf-translate`) for controls of type text box / text area, written as `props._cf.translate`. Three-state like `wysiwyg`: absent = `null`, `pg_cf_translate_flag()` keeps the stored row's flag (1 for a new row); true/false = the canvas wins. Any other field type is always 1. `_cfTranslateOn()` shows `_cf.translate` or the row flag from `_formFieldsCache` (`list_form_fields` returns `translate_values`); `_cfBuildFromFields()` copies `translate` from `pg_cf_load_page_fields()`.
- Classic screens (`add_field.php`, `edit_field.php`): a "Include in translation" row for custom forms, shown for text box / text area by `change_field_type()` (`backend.src.js`). Designer forms stay read-only on `edit_field.php`. Product, shipping and billing forms never write the column.

## Where is it read?
- Extraction: `pg_tr_extract_form_records()` adds `AND form_fields.translate_values = '1'`.
- Render: both widget SELECTs read `translate_values AS field_translate`; `pg_sw_index_form_data()` carries `translate`; `pg_tr_record_fields()` skips values `pg_tr_record_field_translatable()` rejects.
- Every reader probes the column (`pg_schema_has()` / `pg_tr_has_column()`): before the upgrade runs, the widgets still draw and every field counts as included.

## What stays stale after a switch changes?
- Store: the field's `translation_uses` rows stay until the form's records are extracted again (Forms group update, or "Update this page" on a page whose widget includes the records); the owner's rows are replaced then.
- Pages: a translated page body or robots answer kept in a cache keeps its old verdict until the page is drawn again. `pg_tr_render_rules()` was not bumped: right after the upgrade every field is included, so nothing drawn earlier is wrong.

## How is it tested?
- `tests/custom_form_translate_test.php`: the three-state record, `pg_cf_translate_flag()`, the flag in `pg_sw_index_form_data()`, `pg_tr_record_field_translatable()`.

Related: docs/memory/designer/custom-form-rich-text.md, docs/memory/core/translation-translatable-text.md
