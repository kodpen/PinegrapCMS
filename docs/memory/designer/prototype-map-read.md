---
name: prototype-map-read
description: The Prototype view's server read (api.php designer prototype_map, includes/fn/prototype_map.php): what it is asked, what it answers, how hidden pages stay hidden
module: prototype_map
---

## What does the read do?
- `api.php` action `designer`, `sub_action` `prototype_map` (includes/panel/designer.php, open to content-level operators, read-only). Asked: `hrefs` (the design's link hrefs, at most 2,000, each at most 2,048 bytes), `page_ids` (pages of links kept by page id, `pg_proto_map_ids()`), `menus` (classic menu names, at most 50), `regions` (common or designer region names, at most 100); `pg_proto_map_request()` keeps strings only, once each.
- Answers: `env` (`pg_link_resolve_env()`), `maps` (`pg_link_maps_for_items()` of every item the hrefs, menu items and region links name, folded), `form_names`, `hidden`, `draft_folder`, `bin_folders`, `menus` (name → links, null for none by that name), `regions` (name → kind `common`/`designer`/'' and hrefs). The browser twin (platform/link-resolve-js-twin.md) resolves the hrefs from it.

## How are menus and regions read?
- A menu by name (`menus.name`) through `pg_menu_tree()`: `pg_proto_map_menu_links()` walks it in printing order (a child after its parent) giving id, parent, name, href (the page's `PATH . page_name`, else `link_url`), target, page_id, hidden.
- Regions through `pg_common_regions()`; their hrefs by `pg_proto_html_hrefs()` (outside scripts, styles, comments). Dynamic regions are PHP and give no links.

## How do hidden pages stay hidden?
- For a role 2-3 operator a page whose folder `pg_designer_page_access()` calls `hidden` is left out of the maps and the folded index (`pg_proto_map_visible()`); the item names that reach it (any letter case, a pretty address under it) come back in `hidden`: names the editor sent, so nothing new is told. A menu item opening such a page gets no href and page id 0, `hidden: true`. Roles 0-1 see everything.
- A `page_ids` page is read by its name into the same maps; a hidden one comes back only in `hidden_ids` (`pg_proto_map_hidden_ids()`), its name not in `hidden`.
- Drafts and binned pages are not hidden by this: the editor marks them from `draft_folder`, `bin_folders` and the page's `folder` in the maps.
