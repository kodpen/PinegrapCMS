---
name: region-node-to-widget
description: "Make designable" on a classic menu, menu sequence, ad or tag cloud region node: the widget kind and setting per type, where the widget goes, what stays the same.
---

## What does it do?
- The Region panel of a `menu`, `menu_sequence`, `ad` or `tag_cloud` region node with a name shows "Make designable" (`_sdRegionWidgetPropsHtml()`). `_sdRegionMakeWidget()` creates a widget of the matching kind with its starter layout (`_swCreateRow()`), sets the setting that names the region to the node's `regionName`, and puts its `shared_ref` in the node's place. One undo step; the row stays if undone, listed in the design.
- Map `SD_REGION_WIDGETS`: menu -> `menu_region` / `menu_name`, menu_sequence -> `menu_sequence_region` / `menu_name`, ad -> `ad_region` / `ad_region_name`, tag_cloud -> `tag_cloud_region` / `search_page_name` ([[menu-widget]], [[menu-sequence-widget]], [[ad-region-widget]], [[tag-cloud-widget]]).

## Where does it reach?
- Found with `_sdAnyParent()`: a node in the page tree changes this page only; a node inside a shared component (a header) marks that component edited, so every page placing it gets the widget on save.
- Never automatic, never across pages: other pages keep their region nodes. A new widget each time (no reuse of a widget made from the same region before).

## What stays the same?
- The items: menus, ads and keywords are still edited in their own screens with the same rights; the widget reads them on every request.
- What the classic region did that the widget leaves out is listed in each widget's file (menu effects, ad transition type, the `software_tag_cloud` class).

## Tests?
- `tests/region_widget_convert_test.php`: each type maps to a registered kind whose renderer reads that setting.
