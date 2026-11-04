---
name: file-manager-page-where-used
description: The File Manager's "Where used" for a page (explorer_page_usage): menu items, short links, links in content, page settings, products/offers/events and site settings that point at it
---

## What does "Where used" list for a page?
- `pg_explorer_handle_page_usage()` in `includes/explorer/handle_page_usage.php` (the `explorer_page_usage` case), on demand from the panel button or the page's context menu (`loadFileUsage()` sends `page_id` for a page). The page's folder must be visible to the person.
- By id (deleting the page breaks them): menu items (`link_page_id`, or a typed `link_url` that is the page's address), short links (`short_links.page_id`), page settings (every `<x>_page_id` column of `page` and of each `<type>_pages` table, found with `SHOW TABLES` / `SHOW COLUMNS`, named by the owning page with its type), products (`send_to_page`, `email_page`, `*_page_id`), offers (`upsell_action_page_id`), calendar events (`next_page_id`), and four config settings (retrieve-order next page, reward program e-mail, KVKK notice, membership expiration e-mail).
- By address (renaming or moving under another name breaks them): page regions, pages (`page_tree_code`, `page_tree_json`), common regions, styles (code, head, tree), shared components. A `LIKE '%/<name>%'` narrows the rows; `pg_explorer_page_link_found()` keeps only `/<name>` ending where an address ends (quote, `?`, `#`, space, `\`, end), so `/about-us` and `/about/team` do not count for `about`.

## When does a hit link?
- Like files (`file-manager-where-used.md`): pages by `check_edit_access()` of their folder, menu items for role <= 2 or a role-3 menu in `get_items_user_can_edit('menus')`, short links role <= 2, classic styles and regions role <= 1, products and offers with the commerce right, events with `validate_calendars_access($user, true)`; config settings and shared components never.

## Why?
- #5220: a page had no "Where used", so deleting or renaming one broke menus, links and settings silently.

## Tests?
- `tests/explorer_page_usage_test.php`.
