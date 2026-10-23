---
name: shared-component-model
description: How a shared_ref places a shared component: id link and name snapshot, empty children, unique names, nesting without loops, request-time markers, depth cap, deleted rows, editor colours.
---

## How does a page point at a shared component?
- A `shared_ref` node: `props.sharedId` is the link, `props.sharedName` only a display snapshot. A rename (`shared_component` sub-action `rename`) writes `shared_components.name` alone; never rewrite stored trees for it. The editor patches the snapshot in the trees it has open (`_updateSharedRefNames()`) without marking them unsaved.
- `shared_ref.children` is always `[]` in a stored tree: the content lives in `shared_components.tree_json`. `_sanitizeDynamicPlaceholders()` empties it before every save and after load (`isDynamicPlaceholder()`); never write inline-expanded children to the database.
- A taken name is not an error: `_sc_unique_name()` (`includes/panel/shared_component.php`, `create` and `rename`) adds ` [1]`, ` [2]`…; template rows follow the same rule.

## How is it drawn on the site?
- `_render_tree_node()` writes only a marker `<!--pg-shared-ref:ID-->`; `_expand_shared_refs()` replaces it on every request with a fresh render of the row (one query for all ids), so an edit reaches every page without saving them again. A row with `system_region_config` becomes `<!--pg-system-widget:ID-->` for `_expand_system_widgets()`.
- Markers left in a widget's output (a component placed inside a widget) are opened by `pg_expand_nested_components()` (at most four rounds).
- A deleted row draws nothing (an HTML comment `pg-shared-ref:ID missing…` in the source); the next save of the page drops the orphan `shared_ref` with a warning (`_validate_and_clean_tree_json()`).

## May components nest?
- Yes: components and widgets inside each other are allowed (2026.4.8); only a loop is refused, in three places: `canDrop()` (`_sdSharedReaches()`), the save's `_validateTree()` (`_sdSharedCyclePath()`), and the server's `pg_shared_component_cycle()` (page save through `_validate_and_clean_tree_json()`, and sub-action `update`), with a message naming the loop ("Header → Cart → Header").
- Rendering is guarded too: `_expand_shared_refs($html, $depth, $ancestors)` turns a component placed inside itself into an inert comment and stops expanding at depth 8. The `$depth` that `_render_tree_node()` passes along is not a limit.
- New editor code that walks component trees carries `_sdSharedOpen` (or its own ancestor set) so a loop that reached the cache ends instead of recursing forever.

## In what order is it saved?
- Edits are made inline on the canvas. Save first pushes every dirty component (`_flushDirtyShared()`, awaited), then writes the style and the pages: `shared-component-editor-cache.md`.
- Opening a component to content editors: `shared-component-content-editing.md`. Deleting rows outside the editor: `shared-components-panel.md`.

## Which colours mean what in the editor?
- A lock is orange `#f07820`, a shared component green `#22c55e` (`style_designer.css` tree labels and badges, the editing toast).
