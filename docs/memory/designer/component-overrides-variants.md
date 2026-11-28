---
name: component-overrides-variants
description: Named variants of a shared component (CO5, #11687): props._variants on its root, props._variant on a place, the site's place marker, the settings list, editing a variant, the place's "Variant" row
module: component_overrides
---

## Where is a variant kept?
- On the component's root, `props._variants`: a list of `{name, nodes}` in the order added; `nodes` maps a node's `_id` to the props it gives that node. A place keeps only the name, `props._variant` on its placing `shared_ref`. No table or column.
- A variant keeps classes and options only, never content, the reference or a `_…` key (`pg_co_variant_prop_ok()`); values are strings, numbers or switches.
- Names: trimmed, at most 60 characters, unique in any case, never "Default" in English or the editor's language (`pg_co_clean_variant_name()`; the part's `nameWhy()`); at most 50 variants. Duplicate shortens the original name so that "… copy 2" fits the 60 characters (before PR #12241 a long name hung the editor). Rename keeps the old name in `was`, so its places keep it (`component-overrides-variant-places.md`).

## Which functions are there?
- `includes/fn/component_overrides.php` and its twin: `pg_co_variants()`, `pg_co_variant()`, `pg_co_variant_changes()`, `pg_co_place_variant()`, `pg_co_apply_variant()` (unknown or empty: the tree as given, JS the same object), `pg_co_variant_nodes()` (what an edit keeps; a removed string or switch as `''` or `false`).

## How does the site draw it?
- `pg_co_place_marker($values, $variant)` writes the name as `_variant` in the place marker; no variant and no values write none, so every page saved before draws byte for byte as before.
- `_pg_shared_refs_replace()` draws through `pg_co_place_tree()`: the variant first, then the place's values. A gone or empty variant gives the component's own drawing, not drawn again.
- The save keeps a valid name only (`pg_co_clean_tree_places()`); a content editor's save keeps the stored reference, so its variant (`_pg_dm_take_place()`).

## How do the canvas and Preview draw it?
- `_sdPlaceDraw()` applies `_sdPlaceVariant(ref)`, then the values; none while that component's variant is edited (its tree carries it).
- A component with a variant counts as having per-place choices (`_sdCoHasSid()`): its places get "This place" and its elements are read-only there until "Edit the component" (`component-overrides-this-place.md`).

## Where are variants listed and edited?
- "Shared Component" section, after "Can change per place": head "Variants", "Default" then the variants with their count, a three-dot menu (Rename, Duplicate, Delete) and "Add a variant". With none and the part not loaded the core prints "Default" and the button (`_sdCoVarsHtml()`), else the part fills it (`fillVariants()`).
- Keyboard as the drawing's board C6 (`designer-component-overrides-drawing-variants.md`); Delete asks nothing, a Result line with Undo focused.
- Choosing a variant starts editing it (`startVariant()`): one undo step, its props put on the component's nodes, the selection moved into the component, `_sdCoVar = {sid, name, base, sig, depth}`, the line over the canvas (`.sd-co-vline`) with "Done", focused. "Done", Escape (not in a field; an open menu closes first) or "Default" ends it (`variantDone()`): the variant keeps what differs, the component's own props go back, and the steps inside become the one start step (`_sdCoVarCommit()`).
- Undo past the start ends it keeping nothing; Save finishes it first (`_sdCoVarFinish()` in `_flushDirtyShared()`).
- Adding, removing or moving an element meanwhile is undone (`variantGuard()`) with the amber line; a typed text stays the component's.
- The Options panel's dot compares with the component's own props while a variant is edited (`_sdCoVarDefaults()` in `markModifiedProps()`).

## How does a place pick one?
- By the "Variant" row of "This place": `component-overrides-variant-places.md`.

## Tests?
- `tests/component_overrides_variants_test.php` (model, twin, marker, site, save, the part in Node).
