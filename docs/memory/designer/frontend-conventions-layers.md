---
name: frontend-conventions-layers
description: Palette component vs system widget layers, what is production (legacy) vs development (system widget), and the five invariant principles for system widget work.
---

## Which two layers exist and where is each documented?
- Palette component (hero, CTA, pricing, features...): ready blocks the designer drags; pure presentation, binds no data. Guide: `docs/component-development-guide.md`.
- System widget (catalog_listing, express_order, shopping_cart): data-bound regions; `shared_components.system_region_config` + data binding. Rationale/pitfalls/legacy-parity narrative: the `pinegrap-arayuz` skill.
- Both use the same node tree model (`type`/`props`/`children`) and the same canvas; tree schema, node types, auto-wrap and `canDrop` rules are in `component-development-guide.md` sections 1 and 4.
- Writing a NEW palette component: read `docs/component-development-guide.md` end to end. Mandatory constraints: no custom JS, no hardcoded inline style, no extra CSS, only BS 5.3.8 + Bootstrap Icons; `render()` must equal `toHTML()`. A component is not "done" until the checklist in its section 9 is complete.

## Which layer is production and which is development?
- Legacy custom style (`get_catalog.php`, `get_shopping_cart.php`, custom style templates): PRODUCTION, runs on real customer sites. Do not touch; use it as the yardstick for correct behavior.
- System widget (`_render_system_widget_*`, `style_designer.js`): DEVELOPMENT, used on no site yet. Clean up freely.
- On the system widget side keep NO dead code for backward compatibility ("don't break old trees" is invalid: no users). Two mechanisms doing the same job side by side fail silently because the winner depends on the tree (designer edits text in settings, nothing happens because the button is drawn by binding).
- Value yardstick: default template correctness + designer flexibility. The user often resets the template while testing; a wrong default template brings the bug back forever.

## What are the invariant principles?
1. Legacy parity is the yardstick: correct behavior = output of the legacy page type doing the same job (`catalog` / `catalog detail` / `form list view` ...). Do not guess; open the legacy page and compare.
2. A settings-panel switch must be a real switch: if a feature is off, elements bound to it are NOT emitted to the frontend at all and appear faded on the designer canvas. "Visible but non-functional" is forbidden (e.g. a dead button opening an empty offcanvas).
3. Product groups are nested: "products in this group" almost always means products anywhere UNDER the group. Any query looking only at direct `products_groups_xref` membership is suspect.
4. In-code comments are English; user-visible strings use `lang()`.
5. Prices are stored in kuruş (int). A discount percentage yields fractional kuruş → use `round()`, do NOT truncate with `(int)` (else card and feed differ by 1 kuruş).

Related: docs/memory/designer/catalog-listing-system-widget.md
