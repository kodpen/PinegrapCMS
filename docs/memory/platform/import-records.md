---
name: import-records
description: Content import record and comment writers (2026.11.0): a post as a record of the mapped form, field kinds, categories and tags, author account, late featured image, comment threads, undo paths
module: importers
---

## Where is it?
- `includes/import/write_record.php` and `write_comment.php`, the framework's record and comment writers (`import-framework.md`). Tests: `tests/import_record_test.php`; the undo order in `tests/import_user_test.php`.

## Which rows does a post become?
- `pg_import_record_rows()` (pure) over the mapping's fields; a part without a field, or a field of another form, is left out. Title: plain text, else the slug. Body and summary: a rich text area takes the HTML (`html` row), any other text area or text box the plain text (`pg_import_record_plain_text()`). Date: `date` as `Y-m-d`, `date and time` and a text box as `Y-m-d H:i` (as the add screen stores them). Featured image: only a file upload field, by `file_id`.
- Categories and tags may share a field. A text field takes the names joined by ", "; an option field (`form_field_options`) or a reference field (`pg_coll_kind_options()`) the choices whose label or value matches, case ignored: check box and multiple pick list one row each, pick list and radio the first. Unmatched names go into the report as a note; no option or category record is made.

## How is the body prepared?
- Pictures through `pg_import_media_localize()` first, then `pg_sanitize_rich_text($html, 'staff')`: the visitor profile drops a picture outside the site, and one that could not be fetched keeps its old address with a note (`import-media.md`).

## Whose record is it?
- `forms.user_id`: the author's imported account (`<site>:author:<login>`, `import-wordpress.md`), else the user holding the author's address, else 0. `last_modified_user_id` is the importing person. `submitted_timestamp` the post's time. A draft is `complete = 0`. Pretty addresses through `update_submitted_form_address_name()`; no record revision is written for a new record (the add screen writes none either).

## What if the featured image comes after its post?
- The file item may come later in the export. The record then gets an `import_items` row kind `record` keyed `picture-wait:<attachment key>`; `write_file.php` calls `pg_import_record_attach_picture()` after a fetch, which adds the file row when the picture field is still empty and drops the wait row. A wait left behind (fetch failed) makes the undo call the record's delete twice, which is harmless.

## Where does a comment go?
- On a record: page = the form's first `form_item_view_pages` row (`collection = 'a'`), `item_type = 'submitted_form'`; none is skipped with a reason. On a page: the page, item `''` / 0. Comments on files are skipped.
- `parent_id` = the imported comment its `parent` key names (0 when not imported), written only when `pg_comment_replies_ready()`. Held comments unpublished. Plain text; name kept, address dropped (no column), `created_user_id` 0: a commenter is never tied to an account. A published one refreshes the record's counters (`pg_comment_refresh_record_info()`).

## How does undo remove them?
- Record: the submitted forms delete path (forms, form_data, signatures, views, `pg_comment_delete_record_thread()`), files left to their own rows. Comment: the comment screen's delete (row, notifications, counters). Undo runs newest first: comments, then records, then authors.
