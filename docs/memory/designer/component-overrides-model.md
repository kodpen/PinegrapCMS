---
name: component-overrides-model
description: The pure model of a shared component's per-place values: props._open on a component node, props._place on the placing shared_ref, stable field ids, apply, left-overs; PHP and its JS twin
module: component_overrides
---

## Where are the open fields and a place's values kept?
- In the component's tree: a node's `props._open` is a list of `{id, kind, label}`. Kinds: `text` (`props.text`), `picture` (`props.src`, `props.alt`), `link` (`props.href`), `icon` (`props.iconName`), `shown` (false leaves the node out). An entry with an unknown kind or a bad id is ignored.
- On the page: the placing `shared_ref` node's `props._place`, an object id => value. No table or column (`component-overrides-overview.md`).

## What is a field id?
- `of_` and eight random lower-case letters or digits (`pg_co_new_field_id($taken)`); valid ids are a lower-case letter then up to 39 of `[a-z0-9_]` (`pg_co_valid_field_id()`). Random, never counted, so a field opened after another was closed never takes the closed field's stored values.
- An id met twice in a tree keeps its first node; a place that hides that node takes the ids of its subtree with it, so a second node draws as the component has it (#11998).
- A copy never shares ids (`_sdCoCopyFields()` in the core, #11999): Duplicate (Ctrl+D, the canvas and Overview menus), Paste or an Alt+Drag copy inside an ordinary shared component gives each open field of the copy a new id (`_sdCoNewFieldId()`, this form), its label kept; a copy on a page or in a system widget loses `_open`. `_openOrder` and `_variants` never travel with a copy. Before #11999 the copy kept the ids and only the first node took a place's value.

## Which functions are there?
- PHP `includes/fn/component_overrides.php`; the twin `assets/js/pg_component_overrides.js` (`window.PGComponentOverrides`), bundled first in the designer part `overrides` (`component-overrides-fields.md`; drawing: `component-overrides-render.md`).
- `pg_co_open_fields($tree)` / `openFields`: `{id, kind, label, nodeId, value}`, `value` the component's own (a picture `{src, alt}`, shown `true`), `nodeId` the node's `_id` (the designer's) else `id`. A `shared_ref` inside the component is not entered.
- Order: the ids of the root's `props._openOrder` first, in its order (ids naming no field skipped), then the rest in document order (`pg_co_order_fields()`). It is the order of the component's settings list and of "This place" (#11597).
- `pg_co_apply($tree, $values)` / `apply`: the tree as drawn at a place; a copy, the given tree unchanged. Nothing to apply (no values, no open field, only unfit values) gives the tree back as given (JS: the same object), so a component with no open field draws byte for byte as before. The root is never left out.
- `pg_co_clean_value($kind, $value)` / `cleanValue`: text, link and icon strings; shown a boolean; picture `{src, alt}` (a string is the src; no alt keeps the component's). Unfit is `null` and ignored.
- `pg_co_leftovers($tree, $values)` / `leftovers`: ids no open field takes any more, or whose value no longer fits; the Publish check lists them (CO2 on).
- `pg_co_place_values($ref)` / `placeValues`: `props._place` or nothing.

## What does the model not do?
- `pg_co_apply()` checks no address and escapes nothing: drawing runs `pg_co_safe_values()` first (`component-overrides-render.md`); the save cleans a place with `pg_co_clean_place()` (`component-overrides-this-place.md`).

## Tests?
- `tests/component_overrides_test.php`: the PHP functions, and the twin run in Node on the same cases with the answers compared (skipped without Node). CO1, #11520.
