---
name: common-region-queries
description: Classic pages read <cregion> rows through pg_common_regions(): one query per nesting level per request, how a name finds its row (collation, lowest id), what the output must keep
---

## How are `<cregion>` tags read?
- `pg_common_regions($names)` (`includes/fn/content.php`) reads every name it has not read yet in one query and keeps the answer (a row, or none) for the request. `get_page_content.php` asks for all names before each pass: the designer region pass for the content's names, then for the names inside those designer regions; the common region pass only for tags that appeared since (PHP, dynamic and system regions). Pure parts: `pg_common_regions_read()`, `pg_common_regions_query()`, `pg_common_regions_from_rows()` (`tests/common_regions_test.php`).
- Before, both passes ran `SELECT … WHERE cregion_name = '…'` per tag, so a common region named in the style was read twice. Sandbox, Turkish starter site (2026-10-20): home page 17 region queries, now 2 (most starter pages have 2: `footer-1` holds the `twitter-feed` designer region); `/home-3` 23 → 2; 183 pages anonymous 590 → 198; home page 88 → 75 queries in all. The 2nd query is a nesting level, not a tag: names inside designer regions are only known after the first.
- Edit mode for a role 3 user asked `get_items_user_can_edit('common_regions')` per common region; now once per view (fixture page 25 → 1).
- A job rendering many pages in one process (search index, SEO, e-mail bodies) reads each name once: four pages twice, 204 → 8. A region saved meanwhile is not seen until the next request, as with `pg_menu_tree()`.

## How does a name find its row?
- As `cregion_name = '<name>'` did, under the column collation: case, accents (`türkçe` finds `turkce` under `utf8mb4_unicode_ci`) and trailing spaces do not count. The query adds `pg_matches` (positions of the names each row answers, compared by the database with the same `=`), so PHP never imitates the collation.
- `cregion_name` has no unique key. Of several rows a name finds, the lowest id wins, which is what the index order gave a query per tag.
- A missing name leaves the tag as it was; the old code also logged four "array offset on null" warnings per missing tag.

## What must the output keep?
- Byte for byte the old one (compared with `cmp` after masking captcha, clock and view-count values): all 183 sandbox pages as a visitor, as the administrator in edit mode and as a role 3 editor, both page editor versions, and a fixture page with duplicate tags, other spellings, duplicate names, a quoted and an empty name, a missing region, designer regions three deep, a common region holding tags, an upper-case `<CREGION>` tag, tags inside a dynamic region's output.
- The passes keep their loops: tags in the order found, `str_replace()` on the whole content, one level of designer regions inside designer regions, a common region's own tags replaced only if a later tag in the list has that name.

## Where does the pattern remain?
- `<dregion>` tags on a page view: one `dregion` query per tag. Theme designer panel (`includes/panel/designer.php`, designer regions of a style): one per tag. Neither is a common page view on the starter site.
