---
name: form-feed-item-guid-enclosure
description: A form feed item's guid is unique per record (permalink with a detail page, page#code without one); a typed media address gets length 0 and a type from its extension, or no enclosure
module: widgets
---

## Which feeds does this cover?
- The system widget form feed (`pg_sw_form_feed_parts()`, `includes/fn/widgets.php`) and the older form list view feed (`get_page.php`, `?rss=true`). Both call the same two helpers; never copy them.

## What is an item's guid?
- `pg_sw_feed_guid_xml($permalink, $page_url, $reference_code)`.
- With a detail page the record's address (`?r=` or pretty URL) is the link and a plain permalink `<guid>`.
- Without one the item links to the list page, and the guid is `<guid isPermaLink="false">` page address + `#` + the record's reference code.
- Why: every item used to carry the page address as guid; readers de-duplicate by guid, so subscribers saw one item and nothing new ever arrived, and validators failed (issue #4039, PR #4205). The older feed wrote an error text as link and guid; it now links to the list page too.

## What enclosure does a media field give?
- An upload: its real size and type, only when a visitor who is not signed in may open it (`pg_file_open_to_everyone()`).
- An address typed into the field: `pg_sw_feed_url_enclosure_xml($url, $site)`. Size unknown, so `length="0"` (the RSS convention); type from the path's extension through `get_mime_type()`. A site path (`/…`) is made absolute with the site. No extension, an unknown type (`application/octet-stream`) or text that is not an http(s) address or site path: no enclosure, never a made-up one. Before, both feeds wrote `length="50000" type="image/jpeg"` for any typed address.

## Where are the tests?
- `tests/form_feed_items_test.php`.
