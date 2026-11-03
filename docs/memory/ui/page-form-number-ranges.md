---
name: page-form-number-ranges
description: Page form System Options number boxes (quiz pass percentage, day counts, photo gallery thumbnail size) refused out of range by pg_page_form_number_errors() instead of cut by their columns
---

## Which boxes are checked, and where?
- `pg_page_form_number_errors($page_type, $source)` (`includes/forms/page_form.php`, pure) gives field id => message: custom form quiz pass percentage outside 0-100 (only while Enable Quiz is on); trial, private access and offer day counts that are not a whole number of 0 or more (only while their switch is on); photo gallery thumbnail max size below 1. An empty box is left to the stored default.
- Called by `add_page.php`, `edit_page.php` (only when the save writes the type's fields, `$may_save_page_type`) through `pg_page_form_refuse()`, and by the designer's System Options pane (`pg_page_type_pane_request()`, a 422 with `fields`).

## Why?
- The session is not strict, so the columns cut the value without an error: `custom_form_pages.quiz_pass_percentage` is TINYINT and kept 127 of 150 (a quiz nobody could pass), unsigned day counts kept 0 of -5, a 0 thumbnail size was saved (#5249).
- The boxes stay `type="text"` with inputmask; the server check is what holds.

Test: `tests/page_form_save_test.php` (`page_form_number_errors`).
