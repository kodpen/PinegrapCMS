---
name: page-noindex-switch
description: What the per-page noindex/nofollow switch does in three places, which four screens write it, the sitemap query rule and the PHP/JS lockstep rule (2026.4.3).
---

## What does the page `noindex` switch do?
Three things at once; if one is missing it is a bug:
- Emits `<meta name="robots" content="noindex, follow">` at the start of the `$meta_tags` block in `get_page_content.php`.
  On an indexed page the same tag carries noarchive / max-snippet instead (`pg_page_robots_content()`).
- Adds `Disallow` lines to `robots.txt`: `pg_build_robots_disallow_rules()` (`includes/fn/seo.php`) -> `get_robots.txt.php`.
- Drops the page from the sitemap: `get_sitemap_info.php` + `sitemap = 0` forced on the save side.

## How does `nofollow` relate?
- `nofollow` is a dependent switch: it only qualifies the directive and is stored as 0 while `noindex` is off. Three states are meaningful; the fourth cannot be stored.

## Which queries must also ask about `noindex`?
- `sitemap = 1` and `noindex = 1` never belong together. Every new query that reads the `sitemap` flag must also read `noindex`. Besides the home page loop, the catalog detail / form item view / calendar event subqueries read their own `LEFT JOIN page` row; forgetting one leaves that page's item URLs in the sitemap.

## Where is the switch edited?
- FOUR screens: `add_page.php`, `edit_page.php` (legacy page screen), `add_system_style.php`, `edit_system_style.php` (Visual Editor page settings panel). All write the same `page` row; a field added to one is lost when saved from another. In the panel the block is unconditional (Visual Editor gives pages no type; all are `standard` / `system`).
- `pg_page_noindex_ready()`: on an install with new code but an un-upgraded DB the feature silently disappears. It probes BOTH columns (the upgrade runs two separate `ALTER`s). Same pattern as `pg_sfv_stats_ready()`.

## Which PHP and JS code must change together?
- The save-side `sitemap = 0` forcing (`pg_page_form_index_flags()` for the two page screens) and `bindPageIndexingSwitches(ids)` (backend.src.js) state the same rule; update in lockstep. The function takes element ids: page screen defaults (`noindex` / `nofollow` / `sitemap`), panel `pg_page_*`, and a `hint`.
- Never rely on a `disabled` checkbox not being submitted: the POST need not come from that screen. (Page screen sends bare checkboxes, so a disabled one never goes; panel pairs it with hidden `value="0"`, so zero goes. Same result.)

## What if the meta tag placeholder is gone?
- If the `<meta_tags></meta_tags>` placeholder was deleted, the robots meta is still inserted before `</head>`. Anything else in the block may silently drop; the answer to a switch the operator turned on may not.
- Never printed in email bodies (`$email === true`).

## What is the known trade-off?
- A page blocked by `robots.txt` is not crawled, so its `noindex` meta is never read. Right for pages not yet in results (no crawl budget spent); a slow way to drop an already-listed page. Shown as a warning on the screen.

Related: docs/memory/visitors/robots-txt-disallow-rules.md, docs/memory/visitors/page-canonical-robots-directives.md
