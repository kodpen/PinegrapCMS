---
name: designer-component-overrides
description: Plan (owner 2026-11-24): a shared component's open fields changed per place (text, picture, link, shown) and named variants, the rest following the component; steps CO1 to CO6
priority: 7
---

## What is asked?
- Owner (2026-11-24): the seven additions go in (`workflow/decisions-made-product-106.md`). This one: the same card or intro reused with its own words and picture, without detaching it.

## What is there today?
- A shared component is the same wherever it is placed; one marked block can be opened to content editors (`designer/common-region-to-shared-component.md`). A different text means a copy that no longer follows the component.

## What is built?
- In the component: a text, picture, link, icon or an element's "Shown" can be marked "Can change per place", with a label. Overview marks such a node; the component's settings list its open fields in order.
- Where it is placed: the Options panel's first section "This place" lists the open fields, the component's value as the hint. A changed field carries a dot and "Reset to the component". An open text is edited in place on the canvas for this place only; everything else is read-only with "Edit the component".
- Variants: the component can keep named sets of its nodes' classes and options ("Dark", "Compact"); a place picks one from a list. A variant is the component's, a place holds only its name.
- Kept on the placing node of the page's tree, by the open field's stable id: no table or column. The site, the canvas and the Preview apply them when they draw the component.
- A field closed later or a node removed: its values are ignored, never drawn, and listed as left-over by the Publish check once a step hands them in (`designer/designer-publish-check-results.md`).
- A place's text enters the site translation as its own text (`designer/designer-content-translation.md`); a content editor may change a place's open fields on a page that is theirs (`designer/designer-content-roles-save-gate.md`).

## What must it keep?
- A component with no open field, and every page saved before, draw byte for byte as today.
- The page cache is the page's: a place's values never reach another page's copy. A link or picture value passes the checks its own field passes.

## Which steps? (S under 400, M about 1,000 product lines; tests red first)
- CO1 (M) Done (#11520). The model, pure, PHP and its browser twin: the open-field list, stable ids, a place's values applied to a tree, left-overs found.
- CO2 (M) after CO1: site render, canvas and Preview apply them; translation; the cache test.
- CO3 (M) Done (#11597). marking fields open in the component, the list in its settings.
- CO4 (M) after CO2, CO3: "This place", the dot and reset, in-place text, content roles.
- CO5 (M) after CO4: variants.
- CO6 (S) Close-out: memory in `designer/`, this file deleted, the `[NEW]` line named.

## What does every step keep?
- The drawing: `designer-drawings.md`. Claim as `[plan:designer-component-overrides] <step> — <topic>`.
