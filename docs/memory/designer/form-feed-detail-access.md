---
name: form-feed-detail-access
description: A form list's ?rss=true and a member-only detail page: the feed still sends every record's RSS description; bug #11440 waits on the choice between no items and a teaser feed
module: widgets
---

## Does a list's feed look at the detail page's access?
- Not yet. `pg_sw_form_feed_parts()` (`includes/fn/widgets.php`) lists the newest fifty records with their title, description, media and category whatever the folder of the detail page; `pg_sw_list_detail_target()` reads only the page name and the pretty-address flag.
- The classic form list feed (`get_page.php`, `?rss=true`) does the same.
- So a post page in a Membership or private folder keeps the post from signed-out visitors, while `/<list>?rss=true` hands them its RSS description; the item's link opens the sign-in screen.

## What do other outputs do with such records?
- The sitemap lists records only under a public detail page (`get_sitemap_info.php`, `get_access_control_type()` is `public`).
- A viewer-filtered list gets no public feed at all (`record-view-filters.md`).

## What is open?
- Bug #11440: for a detail page that is not public, the feed either sends no items, or sends title and link without the description (and media) as a teaser feed. The choice is asked under `## Decision question` in the fix's PR.

Related: docs/memory/designer/record-view-filters.md, docs/memory/designer/form-feed-item-guid-enclosure.md
