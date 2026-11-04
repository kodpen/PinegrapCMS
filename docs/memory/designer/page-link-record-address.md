---
name: page-link-record-address
description: A record's address (form_item_view) sent by a link to another page is the whole address with the site's origin, so it can be pasted into a browser (#4878); the path when longer than 100 characters.
module: widgets
---

## What value does form_item_view give a page link?
- `pg_sw_page_link_record_value($path, pg_sw_ai_page_url($path))` in both form views (list rows and the detail view) gives the record's whole address (`URL_SCHEME . HOSTNAME_SETTING`, the language path), not `/topic?r=…`.
- The path when the whole address is unknown or longer than 100 characters (a URL filter reads at most 100, `pg_sw_url_param_value()`, so it would be dropped); `''` without a record (`#`).
- Only page-link values change: the `form_item_view` token in an `href` stays the relative address.

## Why?
- The forum's Report a post sent `/konu?r=…`; the report page and the moderators' e-mail show the post's address as text ("copy it into your browser"), and a relative address pasted alone opens nothing (#4878). The Share link already copied the whole address.

## Which test checks it?
- `test_designer_page_link_record_value` (`tests/designer_page_link_test.php`).
