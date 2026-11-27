---
name: component-overrides-overview
description: Start file for "Can change per place" and variants of a shared component: what a place may change, the owner decision, what it keeps, which file holds what
module: component_overrides
---

## What is the feature?
- The same shared component (a card, an intro) placed with its own words, picture, link, icon or "Shown", and a named variant ("Dark", "Compact"), without detaching it; everything else follows the component.
- Owner (2026-11-24): it goes in with the seven additions (`workflow/decisions-made-product-106.md`), because a different text meant a copy that no longer followed the component.

## What does a person see?
- In the component: an element's three-dot menu "Can change per place" opens a field with a label; Overview and the Options rows mark it; the "Shared Component" section lists the open fields in order and the "Variants" (`component-overrides-fields.md`, `component-overrides-variants.md`).
- Where it is placed: the Options panel's first section "This place", the component's value as the hint, a dot with "Reset to the component", an open text typed in place on the canvas, the rest read-only with "Edit the component", and the "Variant" row (`component-overrides-this-place.md`).

## Where is it kept?
- On the component's tree (`props._open`, `props._openOrder`, `props._variants` on its root) and on the placing node of the page (`props._place`, `props._variant`), by a field's stable id: no table or column (`component-overrides-model.md`).

## What must it keep?
- A component with no open field and no variant, and every page saved before, draw byte for byte as before.
- The page cache is the page's: a place's values never reach another page's copy. A link or picture value passes the checks its own field passes (`component-overrides-render.md`).
- A field closed later or a node removed: its values are ignored, never drawn. The Publish check can list them as left-over, but no source hands them in yet (`designer-publish-check-results.md`).
- A place's text enters the site translation as its own text; a content editor may change a place's open fields on a page that is theirs (`component-overrides-render.md`, `component-overrides-this-place.md`).

## Which file holds what?
- `component-overrides-model.md`: ids, apply, left-overs, the PHP and JS twin (`includes/fn/component_overrides.php`, `assets/js/pg_component_overrides.js`). CO1, #11520.
- `component-overrides-render.md`: site, canvas, Preview, cache, draw checks, translation. CO2, #11595.
- `component-overrides-fields.md`: opening a field, the list in the settings. CO3, #11597.
- `component-overrides-this-place.md`: "This place", the lock, content editors, save checks. CO4, #11663.
- `component-overrides-variants.md`: variants. CO5, #11687. A place's variant and Rename: `component-overrides-variant-places.md` (#12001).
- The drawing: `designer-component-overrides-drawing.md`, boards C1 to C7.

## How is a new kind of field added?
- A kind in the model and its twin (`cleanValue`, apply, the safe-value check when it is an address), a switch in the part's list, a row in "This place", and a case in `tests/component_overrides_test.php` run against both.
