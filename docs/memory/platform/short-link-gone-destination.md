---
name: short-link-gone-destination
description: A short link whose page or file was deleted answers its address as no link had it (404 or a pattern), not with the home page; pg_short_link_destination_gone() in router.php
module: short_link_redirects
---

## Which links outlive their destination?
- A file delete (File Manager, Edit File, Edit Files, the bin's purge) keeps the file's short links; page, product group and product deletes remove theirs, other doors may not.

## What answers such a link?
- `router.php` asks `pg_short_link_destination_gone()` (pure, `includes/fn/short_link_redirects.php`) right after the lookup, before the gate: no joined page name for a page, product group or product link, no file name for a file link. A gone link is dropped and the address answers as no link had it: a 404, or a `*` pattern (`short-link-redirects.md`).
- A destination in the Recycle Bin is not gone: it keeps its parked name and answers as its page does (`pg_short_link_binned_original()`). A one-time link to a gone destination is not spent.

## What was the symptom before?
- The empty page name reached `get_page.php` as no page name, which draws the home page: a deleted file's short address answered the home page with 200, a 301/302 link included (`pg_short_link_location()` gave no address and the router fell back to the old answer).

Tests: `tests/short_link_redirects_test.php` (`destination_gone`).
