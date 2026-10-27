---
name: db-q-converted-catalog-forms
description: get_catalog.php, get_photo_gallery.php and custom_form.php converted to db_q() (PR #2291): how values are passed, what stayed raw and why, the guard test.
---

## What was converted?
- `get_catalog.php`: its 15 queries with values (product group lookups by address name and id, the browse lists, the featured and new lists, which now bind today's date). The two top-level group lookups without values stay raw.
- `get_photo_gallery.php`: its 14 queries with values (gallery page, folder lookups, the member check of both views, which bound nothing for `$contact_id` before and now binds it too). The folder and image listings without values stay raw.
- `custom_form.php` (the custom form submit handler): its 18 queries with values: the form page and fields lookups, the `forms` and `form_data` inserts, the upload `files` insert, the membership start page, the new contact and its user link, contact groups, the quiz score and the visitor update.
- Every value is passed `(string)`, as the old SQL quoted it, so the text sent stays the same: `escape(null)` and `(string) null` both give `''`, and a rounded quiz score (a float) prints the same digits.

## What stayed raw, and why?
- In `custom_form.php`, the option e-mail lookup completed by `$where` and the contact update completed by `$sql_update_contact`: both clauses are built with escaped values and may hold a `?`, which `db_q()` would take as a placeholder.
- Its `db()` / `db_value()` / `db_items()` calls with `e()` are untouched: they end the request on failure, which `db_q()` does not.

## How is it guarded?
- `tests/db_q_catalog_gallery_form_test.php`: no escaped or bare value in a raw query's text (outside the two clauses above), no escaped value in a `db_q()` SQL, at least the counts above, and one value per `?` in every `db_q()` call.

Related: docs/memory/data/db-q-converted-files.md, docs/memory/data/db-q-placeholder-helper.md
