---
name: bug-hunt-areas-overrides
description: Bug hunt of "Can change per place" (2026-11-28, PR #12241): the three faults fixed in style_designer_overrides.js and component_overrides.php, and what was read clean
module: component_overrides
---

## Which area, and who named it?
- The first area of `bug-hunt-areas-7.md` (architect, agent channel message 15408): `assets/js/style_designer_overrides.js` and `includes/fn/component_overrides.php`, with the twin `assets/js/pg_component_overrides.js` where a fix needs it.

## What was fixed? (PR #12241, a test each in `tests/component_overrides_variants_test.php`)
- A variant renamed only in case ("dark" to "Dark") lost every place saved on the old name: the old name was dropped as a former name, because a name now holds it in any case. `pg_co_variant()` / `variant()` now also match a name now in any case, after the exact name and the former names (`designer/component-overrides-variant-places.md`).
- Duplicate of a variant whose name is 55 characters or more hung the editor: the copy's name, cut back to 60 characters, was one already taken and the loop never ended. `duplicateVariant()` shortens the original name instead.
- "Default" was refused as a variant name only in the editor's language; in a translated editor a variant could be called "Default" and read as a second default row. `nameWhy()` refuses both.

## What was read clean?
- The PHP model: open fields, order, apply, hidden nodes claiming their ids, left-overs, `pg_co_clean_place()`, the place marker and its decoding, `pg_co_safe_values()` (an entity without its `;` stays inert because the site prints an address through `h()`), the place's translated texts, variants and `pg_co_variant_nodes()`.
- The part: the field list and its labels, the settings list (Rename, drag, Alt+Up and Alt+Down, Close the field with Undo), "This place" (values, refusal, Escape, reset, the picker), typing in place, adding, renaming, deleting and editing a variant, and a place's "Variant" row.

## What was left?
- No issue opened. Small edges seen and not fixed: a field drag ended by `pointercancel` keeps its listeners until the next release; Undo of "Close the field" after the same kind was opened again gives the element two entries of that kind.

Related: docs/memory/workflow/bug-hunt-areas-7.md, docs/memory/designer/component-overrides-overview.md
