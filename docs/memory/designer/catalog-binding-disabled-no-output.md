---
name: catalog-binding-disabled-no-output
description: Disabled catalog feature emits no output: _pg_catalog_binding_disabled(), designer mirror _sdCatalogBindingDisabled(), which settings disable which bindings.
---

## What happens to nodes bound to a disabled feature?
- Frontend: `_pg_catalog_binding_disabled()` (functions.php) drops nodes whose binding depends on a disabled feature from the tree. It runs in the child-filtering phase, so when `search_form` is dropped its input + button go too.
- Designer: `_sdCatalogBindingDisabled()` (style_designer.js) is the MIRROR of the same rule; update both in lockstep.
- The node gets `.sd-binding-off` (faded + grayscale + "KAPALI" badge). Do NOT set `pointer-events:none`: if it cannot be selected it cannot be fixed.

## Which setting disables which binding?
- `action=search_form` / `submit_search`, `value=search_query` → `search_enabled` off.
- `action=open_filters` / `clear_filters`, `section=filters_offcanvas` / `attribute_filters` → only when price + stock + attribute filters are ALL off.
- `filter_chips` / `active_filters` are NOT dropped: they also carry category and search pins, and render nothing when empty anyway.

## What must filter switches in the designer do?
- Filter switches must call both `render()` AND `renderProperties()`, otherwise the canvas does not show the fading.

Related: docs/memory/designer/catalog-listing-system-widget.md
