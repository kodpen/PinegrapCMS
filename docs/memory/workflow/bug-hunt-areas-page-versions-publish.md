---
name: bug-hunt-areas-page-versions-publish
description: The bug hunt of page drafts, scheduled publishing and revisions (2026-11-24, hunt 35): the three faults fixed, the finding left as an issue, what was read clean
---

## Which area was hunted?
- Hunt 35, page-versions-publish: drafts of visual-designer pages (`designer/page-drafts.md`), scheduled publishing (`includes/fn/page_schedule.php`), page revisions and their restore (editor History, `POST /design/pages/{id}/revisions/{no}/restore`), two people saving one page (`includes/designer_collab.php`), a page deleted while a menu item or a short link points at it.
- Asked for: a draft never public, in the sitemap, a feed or search; a scheduled move at the site's time zone and across a clock change; a restore keeping address and SEO fields.

## What did the hunt's PR fix?
- A publish time typed in the hour the clocks skip in spring (02:30 in Europe/Berlin on 29 March) was refused as "not a date and time" and the whole multi-page save failed. `pg_page_schedule_parse()` now checks the text against the calendar in UTC and reads it in the site's zone: the moment right after the jump. Test: `tests/page_schedule_test.php`.
- A draft's or a binned page's "promote on keyword" terms stayed in `tag_cloud_keywords` (a move into the Drafts folder changes only `page_folder`) and the three tag cloud reads (classic region, both widget reads) showed them to visitors. They now append `pg_tcw_hidden_page_sql(pg_page_off_site_folder_ids())`. Test: `tests/tag_cloud_widget_test.php`.
- A menu item with its access switch off linked a draft or binned page for every visitor, naming it and leading to the sign-in page. `pg_menu_item_shown()` (includes/fn/content.php) checks view access for such a page whatever the switch says; the classic menu and the Menu widget (`pg_sw_list_menu_rows()`, fifth argument) use it. Test: `tests/menu_widget_test.php`.
- `pg_page_off_site_folder_ids()` (includes/fn/designer.php): the Drafts folder plus the recycle bin and its folders.

## What was left as an issue?
- A tab whose lock lapsed (laptop asleep past `PG_COLLAB_STALE`, 65 s) while another person opened, saved and closed the page takes the lock back on its next beat and its save writes its old tree over the other person's work without a word. The save has no base version to compare: `page_timestamp` also moves with the job's scheduled move, which an open editor must survive, so the guard needs a decision (a counter column or a tree hash sent back).

## What was read clean?
- Draft reach: sitemap (`$public_folder_ids`), IndexNow, llms.txt, both site searches and the Search Results widget, ActivityPub outbox, recent comments, folder views, hreflang (own page only), the open `api.php` actions; the router sends a visitor at a draft to sign-in.
- Schedule: times stored as unix, parsed in the PHP default zone (the site's), the job compares unix times, clears a time with `AND col = <read>` so overlapping runs move a page once, bumps the page cache (the sitemap cache shares its generation). The hour that comes twice in autumn is read once (PHP takes the later one).
- Restore: the API reads every stored field through `pg_designer_load_pages()` and writes only the tree; History loads the tree as unsaved work and leaves settings alone.
- Delete: both delete paths drop the page's short links (an old address then answers 404); a menu item of a deleted page stays as text without a link; File Manager's "Where used" lists both before a delete.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/designer/page-drafts.md, docs/memory/designer/page-schedule.md
