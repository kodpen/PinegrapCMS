---
name: get-file-attachment-cache
description: get_file.php sends a submitted-form or comment attachment Cache-Control private in any folder (#7667); a list view whose item view page is gone grants nothing through it (#7668)
---

## Which cache header does a form or comment attachment get?
- `Cache-Control: private, max-age=604800`, whatever its folder's access type: `files.attachment = 1` sets `$cache_control_type = 'private'` beside the workspace, designed-page and negotiated-image rules (`get_file.php`).
- Why: the attachment check (submitter security, the views that print the field) runs in `get_file.php`, which a shared cache never reaches. A CV uploaded into the default public folder was sent `public`, and a CDN that caches by extension served it to the next anonymous request. Copies already in a shared cache fall out within the week.

## What if a list view's linked item view page is gone?
- `form_list_view_pages.form_item_view_page_id` is not cleared when the item view page is deleted. The fallback through that page now needs its row and its folder (`is_array($form_item_view)` and a non-NULL `folder_id`); without them that list view grants nothing and the loop goes on.
- Why: `check_view_access(null)` reads as public and `null['submitter_security']` as off, so an RSS media field (`attachment_field_media`) of a record no view shows was open to anyone, against "a record no view shows stays refused" (`workflow/decisions-made-files.md`).

## Tests?
- `tests/get_file_attachment_cache_test.php` (source pins).

Related: docs/memory/core/get-file-shared-code.md, docs/memory/designer/designer-form-uploads-for-readers.md
