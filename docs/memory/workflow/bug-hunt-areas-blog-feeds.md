---
name: bug-hunt-areas-blog-feeds
description: Bug hunt of posts, categories, tags and RSS feeds (area 39, 2026-11-24): two faults fixed in the hunt's PR, two issues, what was read clean
---

## What was the area?
- A blog is a form's records: the designed list and record widgets (`_render_system_widget_form_list()`, `pg_sw_form_feed_parts()`, `pg_sw_list_paging()` in `includes/fn/widgets.php`), the classic Form List View (`get_form_list_view.php`), the page feed (`get_page.php`, `?rss=true`), the sitemap (`get_sitemap_info.php`), categories as references (`includes/fn/collections.php`).
- Asked: drafts, scheduled and members-only posts in lists, feeds, counts and previous/next links; paging at its edges; a renamed category's old address; the feed's dates, escaping and absolute addresses; an author removed from the team.
- There is no previous/next post link: a record view has none; previous/next are the list's page links.

## What did it fix? ("[iş] Bug hunt — blog and feeds" lists each with file and line)
- The classic list, its `?rss=true` feed (both queries) and the classic sitemap listed incomplete records (Save for Later, or a form waiting for its payment); every designed path reads `forms.complete = 1`. `pg_form_list_view_complete_sql()` (`includes/fn/forms.php`) adds it; a list with Viewer Filter on still shows the viewer's own, so a submitter can finish one. Tests: `tests/form_list_view_complete_test.php`.
- The classic page feed built item links and guids on `HOSTNAME`, the request's Host header, so a spoofed or second host name (www and bare) changed every link and guid; now `HOSTNAME_SETTING`, as `init.php` asks of a feed. Tests: `tests/page_feed_host_test.php`.

## What became an issue?
- #11439: a renamed post or category gets a new address name; the old pretty address answers 404 and `?category=<old name>` lists nothing. Keeping it needs a decision (301 short link, or earlier names kept).
- #11440: a list's feed sends each post's RSS description even when its detail page is in a Membership folder; the sitemap leaves such records out. No items or a teaser feed is a product choice.

## What was read clean?
- Drafts and scheduled posts: the designed list, its count, its flags and its feed run the same filters (the list's merged with its detail page's), complete records only; the live rule compares with now on each request (`designer/design-template-news-publishing.md`).
- Paging: the page asked for is clamped to 1 .. last (`pg_sw_list_current_page()`, an array or text gives 1), the offset and Max Results cap the last page's LIMIT, the order ends on `forms.id` (designed and classic), page 1 adds no canonical number. The classic list clamps the same way.
- Escaping: titles, links, guids, categories and the channel through `h()` once; rich text flattened before it; a typed media address only as an http(s) or site path with a known type.
- Dates: `pubDate` in RFC 822 with the offset (`date('r')`); the item date is `submitted_timestamp`, a known follow-up in the news template file.
- Absolute addresses: the designed feed builds everything on `URL_SCHEME . HOSTNAME_SETTING`; autodiscovery in the head is site-relative, which readers accept.
- An author removed: the submitter's name comes through a LEFT JOIN and prints empty; BlogPosting falls back to the contact, then the site; an author archive for a deleted name lists nothing and is noindex.

## What was left as it is?
- A category reference whose record is gone prints its code as the feed category: decided in `designer/collection-lists.md`.
- The classic list's pretty record address is the list page's (`/blog/<name>`), not the detail page's: that is how classic pages route it.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/designer/form-list-url-filters.md, docs/memory/designer/form-feed-item-guid-enclosure.md, docs/memory/designer/collection-lists.md
