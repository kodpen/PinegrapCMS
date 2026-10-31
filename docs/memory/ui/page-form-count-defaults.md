---
name: page-form-count-defaults
description: Create/Edit Page and the designer's Page Type pane save an emptied or zero Photo Gallery column count or Form View Directory summary box as the form's default (4 / 30 / 5), not 0
---

## What does a save store for an emptied count box?
- `pg_page_form_type_properties_from_post()` (`includes/forms/page_form.php`) saves a value below 1 (blank and non-numbers included) as the default the form draws for a new page:
  - Photo Gallery `number_of_columns`: 4.
  - Form View Directory `summary_days`: 30; `summary_maximum_number_of_results`: 5.
- The catalog `number_of_columns` already did the same (stored as 1).

## Why?
- The columns are INT UNSIGNED and the session sql_mode is not strict (`includes/fn/core.php`), so a blank was stored as 0. The site reads 0 as none: `get_photo_gallery.php` printed an empty gallery on a system layout; the directory summary said "last 0 days" with `LIMIT 0` and listed nothing. The edit screen then showed 0, not the default.

## Which zeros are kept on purpose?
- Photo Gallery Thumbnail Max Size 0 (a full-size image) and Calendar View Number of Upcoming Events 0 (all events).

## Tests
- `tests/page_form_blank_defaults_test.php`.
