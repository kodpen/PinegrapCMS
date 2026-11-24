---
name: bug-hunt-areas-site-forms-builder
description: The bug hunt of building a form in the panel (area 44, 2026-11-24, #11453): the five faults fixed, what was read clean, what was not run
module: custom_form, submitted_form_list
---

## Which area was hunted?
- Area 44 "site-forms-builder": building a form in the panel and what its entries keep.
- Read: the designer sync (`pg_cf_reconcile_page_form()`, `pg_cf_sync_page_form()`, `_pg_cf_controls()`, `_cfRenameField()`), the rules (`pg_cf_shown_when_hidden()`, `pg_cf_rule_verdict()`, `pg_cf_validation_script()`), Duplicate Page (`duplicate_page_f.php`), the Forms screen's filters, counts and export (`view_submitted_forms.php`, `includes/fn/submitted_form_list.php`), the readers' rights (`edit_submitted_form.php`, the list source).

## What did the hunt fix?
- Export: headings and values were two queries ordered by sort order alone, so tied fields could swap columns (`ui/submitted-forms-export-columns.md`).
- Duplicate Page copied a classic form's fields without quiz answers, RSS roles, patterns, option addresses and pick list targets, copied a W22b page's fields twice, and gave a designed form default settings (`designer/custom-form-page-copy.md`).
- A "Shown when" rule naming its own field hid the field on the server only (`designer/custom-form-shown-when.md`).
- Renaming a field left the other fields' "Required when" / "Shown when" on the old name, so they quietly stopped (`designer/custom-form-sync.md`).
- The designer's Field name box let a diacritic twin in (`designer/custom-form-field-name-fold.md`).

## What was read clean?
- Rename and reorder keep the row id and its answers; a removed control deletes its row and answers with the `lost_values` warning, as decided (`designer/custom-form-sync.md`).
- Rules: hidden fields are blanked before the checks, the required-when switch skips disabled controls, a group box never carries `required`, office use only fields post nothing for a visitor in either place.
- Entries list: field filters are EXISTS conditions, [All] counts the offered forms, a stale form falls back to [All], the folder rule is part of the query and of Delete Selected; `edit_submitted_form.php` refuses a manage-forms user outside the form's folder.

## What was left?
- A custom e-mail body or subject naming `^^old_name^^` is not rewritten by a rename (auto bodies are regenerated).
- Export of more than one form is refused only by the browser (`export_forms()`).

## What was not run?
- No database or browser here: unit tests of the pure parts, source checks, `php -l`, `node --check`.
