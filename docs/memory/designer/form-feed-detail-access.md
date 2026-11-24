---
name: form-feed-detail-access
description: A form list's ?rss=true item whose detail page is not public is a teaser (title, link, guid, pubDate, category; no description, no media), whoever asks; both feeds (#11462, bug #11440)
module: widgets
---

## What does a feed item of a member-only post send?
- Owner (2026-11-24): a teaser. An item whose detail page sits in a folder whose `get_access_control_type()` is not `public` (Membership, private, registration, guest) sends title, link, guid, pubDate and category; no `<description>` and no media `<enclosure>`. No list option.
- The feed answers as for a visitor who is not signed in, whoever asks: a signed-in member reading the feed gets the same teaser.
- Before #11462 both feeds sent every record's RSS description whatever the detail page's access, while the item's link opened the sign-in screen.

## Where is it decided?
- `pg_sw_feed_teaser_access($access)` (pure): true for any access but `public`; `''` (no detail page) is false, as the item then opens the feed's own page, which must already be public for a feed.
- `pg_sw_feed_page_access($page_id)`: the page's folder access, `''` for no page.
- Designed feed: `pg_sw_form_feed_parts()` reads `pg_sw_form_feed_pick()`'s `detail_page_id` (the record page itself for a `form_item_view` feed, the list's detail page when `pg_sw_list_detail_target()` found one, else 0) and builds each item with `pg_sw_feed_item_xml($item, $teaser)`; a teaser skips the media query.
- Classic feed (`get_page.php`, form list view page): `$rss_teaser` from `$form_item_view_page_id`; both item builders (subquery and old-MySQL join) drop the description, and the media is blanked.

## What else keeps such records out?
- The sitemap lists records only under a public detail page (`get_sitemap_info.php`).
- A viewer-filtered list gets no public feed at all (`record-view-filters.md`).
- A feed page that is not public answers no feed (`get_page.php` checks `$access_control_type == 'public'`).

## Where are the tests?
- `tests/form_feed_detail_access_test.php`: the access rule, the full and teaser item, and source checks that both feeds call the rule.

Related: docs/memory/designer/record-view-filters.md, docs/memory/designer/form-feed-item-guid-enclosure.md
