---
name: record-media-field-share-image
description: A form's RSS media upload opens with the record (every public view counts as printing it); og:image and the feed enclosure skip an upload an anonymous visitor cannot open (#2191, #2174)
---

## When does a record's RSS media upload open to a visitor?
- The form field with `rss_field = 'media'` counts as printed by every public view that shows the record: in `get_file.php`'s classic checks (`$attachment_field_media`, read with the attachment field, in both the list view and the item view layout checks) and in the designed rule (`pg_record_file_view_grants()` reads the `media` fact; `pg_record_file_designed_access()` skips the tree walk for it). The software already publishes that field's address as the record's picture (og:image, the feed's enclosure), so a cover marked as media opens to readers like a printed field (decision #2483 for #2191). A record no view shows stays refused in a public folder too (`workflow/decisions-made-files.md`).

## How do og:image and the feeds avoid the sign-in screen?
- `pg_file_open_to_everyone($file_id)` (`includes/fn/seo.php`) gives the answer `get_file.php` gives a visitor who is not signed in: `pg_record_file_anonymous_access()` (`includes/record_files.php`): a public folder, the attachment flag, no comment attachment, then the record's classic views judged for such a visitor (`pg_record_file_classic_anonymous_access()`, the rule of `get_file.php` over the rows it reads) or the designed widgets (`pg_record_file_designed_access(…, true)`, pages judged by their folder's own setting). A file that cannot be read stays closed.
- `pg_resolve_submitted_form_og_image()` hands an upload to `pg_og_image_from_open_file()`, which returns nothing for a closed one, so `get_page_content.php` falls back to the default share image; the classic feed (`get_page.php`) and the RSS widget (`widgets.php`) write no enclosure for a closed upload. Before (#2174), kodpen.com's share card pointed at `registration_entrance.php`.
- Not decided, left out: an automatic fallback to another image field or the content's first picture when no media field is set.

Tests: `tests/record_files_test.php` (the media fact, the classic rule against `get_file.php`, the anonymous answer fails closed), `tests/seo_share_image_access_test.php` (the resolver asks first, the feeds ask first).
