---
name: custom-form-field-name-fold
description: The designer's Field name box refuses a name another field of the form has as the database compares it (no case, no diacritics), through _cfNameFold() (#11453)
---

## How does the designer compare field names?
- The `sd-cf-name` change handler refuses a name another field has after `_cfNameFold()`: NFD, combining marks dropped, lower case ('sehir' = 'şehir', 'İl' = 'il'; a dotless 'ı' stays apart, as in `utf8mb4_unicode_ci`). It compared case only, so a diacritic twin reached `form_fields`, where the export, the import and the API find a field by its name (`ui/form-names-distinct.md`).
- The server sync still dedupes by `mb_strtolower()` (`pg_cf_sync_page_form()`): a form that already holds a twin keeps both rows, because folding there would delete the second row and its answers on the next save.

## Tests?
- `tests/custom_form_field_name_test.php`.
