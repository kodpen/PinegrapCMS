---
name: tag-cloud-widget
description: Tag Cloud widget (tag_cloud_region): the classic <tcloud> region in the designer; which keywords, their order, size and weight, the search links, translation, what is left out.
---

## What is it?
- Kind `tag_cloud_region` (`includes/fn/widgets_tag_cloud.php`; not `tag_cloud`, the region node's type), label "Tag Cloud". `cfg.search_page_name` names a search results page as `<tcloud>name</tcloud>` does; the picker and a new widget's default also offer the pages carrying the Search Results widget (`regionData.tag_cloud_widget_pages` from `pg_sw_widget_pages('search_results')`, merged by `_sdTagCloudPageNames()`); the classic region node keeps only the classic pages. See [[classic-regions-in-designer]].
- The keywords stay on the products and pages (`tag_cloud_keywords`, `tag_cloud_keywords_xref`); read on every request. No Messages block, no edit chip (no one screen edits a cloud).

## Which keywords, in which order?
- `pg_tcw_keywords()`, as the classic region: the 25 used most by the page's catalog items (through the xref, joined rather than the classic OR of every item) when the page searches them, else by the site's pages. `pg_tcw_rows()` sorts them as the classic `sort()` does (keyword, then count).

## What if the page is built with the Search Results widget?
- A site installed from a design template has no classic search results page, so the picker was empty and the widget drew nothing (#4480, PR #NNNN). When the name is not a classic page, `pg_tcw_widget_search_page()` finds the page's Search Results widget and `pg_tcw_widget_keywords()` reads by its `search_scope`: the pages' keywords (`tag_cloud_keywords`, `item_type = 'page'`) and, for products, the keywords on the product groups shown as a selection and on the enabled products of the browse groups under its `product_group_id` (root when 0), read live, since the xref rows exist only for classic pages. `pg_tcw_merge_keywords()` (pure) adds them, case folded, and keeps the 25 used most. Links go to that page with `?query=`.

## What does a row hold?
- `__tag_name` (translated with `pg_tr_text()` on a page in another language), `__tag_keyword` (as stored), `__tag_url` (`OUTPUT_PATH` + page name + `?query=` stored keyword: the search matches the stored word), `__tag_count`, `__tag_size` (the classic size, 100-200 percent), `__tag_weight` (1-5 from the size, steps of 25).
- Flags `has_tags`, `no_tags`. Static tokens `__search_page_name`, `__tag_total`.

## How is the size drawn?
- Not as the classic inline `font-size: N% !important` (`style` is refused as an attribute value, [[widget-attr-bindings]]). The starter row holds five links, one per weight, each with `show_if __tag_weight eq N` and a Bootstrap size class (`fs-6` .. `fs-2`, about 100% to 200%): real elements a design restyles one by one.

## What is left out?
- The classic `software_tag_cloud` class and the `&nbsp;` separators: the starter is a flex row with gaps. A `tag_cloud` region node becomes this widget with "Make designable" ([[region-node-to-widget]]).
