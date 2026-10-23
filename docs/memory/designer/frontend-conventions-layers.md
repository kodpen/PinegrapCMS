---
name: frontend-conventions-layers
description: Palette component vs system widget layers and where each is documented, legacy pages as production and yardstick, no dead code in the widget layer, the invariant principles for system widget work.
---

## Which two layers exist and where is each documented?
- Palette component (hero, CTA, pricing, features...): ready blocks the designer drags; pure presentation, binds no data. Rules: palette-component-rules.md (summary of `docs/component-development-guide.md`) and palette-component-atomic.md.
- System widget (catalog_listing, express_order, shopping_cart...): data-bound regions; `shared_components.system_region_config` + data binding. Rules: system-widget-bindings.md, token-dictionary.md, system-widget-render-rules.md, system-widget-type-and-name.md, system-widget-change-checklist.md.
- Both use the same node tree model (`type`/`props`/`children`) and the same canvas; tree schema, node types, auto-wrap and `canDrop` rules are in `component-development-guide.md` sections 1 and 4.

## Which layer is production and which is the yardstick?
- Legacy custom style (`get_catalog.php`, `get_shopping_cart.php`, custom style templates): PRODUCTION on customer sites. Do not touch it; its output is the yardstick for correct widget behaviour (workflow/decisions-made-product.md).
- System widget (`_render_system_widget_*`, `style_designer.js`): the layer being developed. It keeps NO dead code for backward compatibility: two mechanisms doing the same job side by side fail silently because the winner depends on the tree (the designer edits a text in settings, nothing happens because the button is drawn by binding).
- Widgets do reach sites (the design templates and the install wizard's template step, platform/design-template-install-at-setup.md), so a saved tree is brought forward where it loads or is selected (`_sdMigrateStructuralBindings()`, `migrateNodeLinkProps()`, `prefetchShared()`) or answered by a renderer alias (token-dictionary.md), never by keeping a second mechanism.
- Value yardstick: default template correctness + designer flexibility. The operator often reloads the default layout while testing; a wrong default layout brings the bug back every time.

## What are the invariant principles?
1. Legacy parity is the yardstick: correct behaviour = output of the legacy page type doing the same job (`catalog` / `catalog detail` / `form list view` ...). Do not guess; open the legacy page and compare.
2. A settings-panel switch must be a real switch: if a feature is off, elements bound to it are NOT emitted to the frontend at all and appear faded on the designer canvas. "Visible but non-functional" is forbidden (e.g. a dead button opening an empty offcanvas): catalog-binding-disabled-no-output.md.
3. Product groups are nested: "products in this group" almost always means products anywhere UNDER the group. Any query looking only at direct `products_groups_xref` membership is suspect (catalog-listing-system-widget.md).
4. An offer's percentage price comes whole from `pg_percent_off()` (`get_discounted_product_prices()`, commerce/offer-percent-rounding.md); a price worked out anywhere else with a fraction is rounded with `round()`, never cut with `(int)`, or card and feed differ by one kuruş.

Related: docs/memory/designer/catalog-listing-system-widget.md
