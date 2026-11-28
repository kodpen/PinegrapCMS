---
name: component-overrides-variant-places
description: A place's variant of a shared component: the "Variant" row of "This place", a gone variant, the palette, and Rename keeping the places on other pages by the old name in `was` (#12001)
module: component_overrides
---

## How does a place pick one?
- "This place" starts with the row "Variant" (a list of "Default" and the variants) when the component has one; not at content level. Another than "Default" carries the dot and counts as a change; its reset returns to "Default". A gone variant: an amber line "The variant … no longer exists" until another is picked or the row is reset.
- Command palette: "Add a variant" (component of the selection), "Variant: name" per variant for a selected place.

## What does Rename do to the places that use a variant? (#12001, PR #12030)
- The places live in other pages' trees, which a rename does not write, so the variant keeps its old name in `was` (a list on its `_variants` entry) and `pg_co_variant()` / `variant()` answer to it: a place saved with the old name keeps drawing the variant on the site, the canvas and Preview, and "This place" selects it under its new name. Before, every such place fell back to the default with the amber "no longer exists" line.
- A name a variant has now wins over a former one (exact case), then a former name (exact case), then a name now in any case: a rename that changes only the case ("dark" to "Dark") keeps its places, since its old name cannot stay a former name.
- Names are unique in any case, so the any-case match finds one variant at most. Before PR #12241 the places of a case-only rename fell back to the default. `pg_co_variants()` drops a former name that is a variant's name now (any case) or that an earlier variant keeps; at most 20 per variant; a variant never renamed has no `was`.
- Rename and "Add a variant" take the new name off every variant's `was` (`forgetFormer()`), so a place on that name follows the variant that has it now. Duplicate copies no former names. Delete removes them with the variant, so its places show the default, as its Result line says.
- A place keeps its stored name until it picks again; then it writes the name the variant has now.

## Why former names, not renaming the places or a stable id?
- Architect (2026-11-27): former names, resolved when a place is drawn. Nothing is written to other pages' trees, so their locks, drafts and versions stay theirs; nothing a place stores changes; no migration; every page saved before draws as before. Renaming `_variant` in every page on the component's save would write other pages; a stable variant id would need a migration of the names stored in page trees, which a rename is not worth.

## Where is it tested?
- `tests/component_overrides_variants_test.php`: the model and its twin (`test_component_overrides_renamed_variant_keeps_its_places`) and Rename in the part (`test_component_overrides_variant_rename_in_the_part`).
