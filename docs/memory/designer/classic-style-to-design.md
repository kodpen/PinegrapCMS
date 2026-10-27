---
name: classic-style-to-design
description: A classic style opened in the visual designer (L2, #2370): the style list button, the frame as one shared component, page trees placing it, what Save writes and what stays
module: classic_style_design
---

## How is a classic style opened?
- `view_styles.php` shows "Open in the visual designer" (bi-magic) on a row with `style_type = 'custom'` for roles 0-1; it links to `edit_system_style.php?id=<style>&classic=1`.
- `pg_classic_style_design_open()` (`includes/fn/classic_style_design.php`) refuses a role above 1 and a row `pg_classic_style_refusal()` names: already a visual design, not a code style (an older cell-based system style), no code.

## Where does the style's frame go?
- Owner answer (b), 2026-10-27, given through the coordinator ("bekleyen işçilere yanıtı sen ver benim yerime", "en makul ve işlevsel seçimle"): one shared component, the same source rule as R2. Reason: one place to edit the frame for every page; a copy per page would have nowhere to edit it.
- `pg_classic_style_frame()` runs the L1 importer with `keep_framework` (the theme's `{path}bootstrap.css`/jQuery stay as design assets). The body's children become the component tree (category `classic_style:<style_id>`, `pg_classic_style_component()`); an unused row made before is brought back to the style's code, a placed one is left alone.
- Each page naming the style (`page_style`, not binned, `pg_designer_load_pages(..., true)`) gets `pg_classic_style_page_tree()`: body attributes and title on the root, one `shared_ref` child. A page with a tree of its own keeps it. `<cregion>` tags stay region nodes (common or designer); making them components is L3.
- Region tags inside the component render per page: `_expand_shared_refs()` runs before the region passes of `get_page_content.php`.

## What happens before and at Save?
- Opening writes only the component row. The pages arrive with `converted: 1`, so their tabs start unsaved (baseline null in `_pgTabsInit()`); the editor opens with framework `custom` and the style's head assets in the asset panel.
- Save (`pg_designer_screen_post()`): `$converting` when the stored row is not `visual_designer`; a content-level save is refused. `save_system_style()` sets `style_type = 'system'`, `style_layout = 'visual_designer'`; then `style_framework` gets the posted key (an update never changes it otherwise). `style_code` stays: the fallback of a page with no tree. `layout_type`, `collection`, `theme_id` stay; pages keep `page_type`, `layout_type`, `layout_modified`.

## What is not covered?
- A page that takes the style from its folder is not one of the design's pages and keeps drawing `style_code`; `select_style()` no longer offers the style to folders.
- A page skipped at Save because somebody else holds its lock stays without a tree; the next open of the design shows it empty.
- Not verified in a browser or against a database: only the pure functions are tested (`tests/classic_style_design_test.php`).
