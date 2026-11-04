---
name: widget-repeat-placement-ids
description: A system widget drawn twice on one page (header and offcanvas): the per-placement id suffix of its static part and of its main loop's rows, which renderers use it, the fault it fixed.
module: widgets
---

## Why does a placement need its own ids?
- A page may draw one widget twice (a menu or language switcher in the header and in an offcanvas menu). Ids must differ between the copies, else a `data-bs-target` or `aria-controls` in the second copy points at the first one (a collapse opens the header's row).

## How are they made unique?
- The static part takes `pg_sw_uniquify_row_ids($html, $widget_id, 1000 + n)`, n the placement's number in the request (a `static $placement` counter per renderer).
- A main loop row takes its row suffix (`r<row>`) and, from the second drawing of the same widget id on, the placement's suffix too: `pg_sw_uniquify_loop_row_ids($html, $widget_id, $row_id, $again)`, `$again` = n when `pg_sw_widget_drawn_before($widget_id)`, else 0. The first drawing keeps the ids it always had.
- Rows of lists (`menu.children` …) were already unique: the finish pass suffixes them with the template and the copy (`pg_sw_lists_expand()`).

## Which renderers use it?
- `menu_region`, `language_switcher`, `google_reviews`, `tag_cloud_region`, `folder_view`, `ad_region` (ad rows: before the ad's markup goes in, so ids inside an ad stay as written). The account and cart widgets suffix their whole output per placement instead.

## What did it fix?
- Before, the loop rows of the second copy had the same ids as the first (`more_pgw5r1` twice): only the static part was made unique.
- `folder_view` made no id unique per placement, static part included, until #5060. Test: `tests/widget_repeat_placement_test.php`.
