---
name: delivery-tower-segments
description: The link segments "Problems" / "Carrier scorecard" under the head of Delivery problems and the Carrier scorecard (DT5b): who sees which, how they are drawn
module: delivery_tower
---

## What are they?
- Architect (2026-11-21): under the head of Orders › Delivery problems (`view_delivery_problems.php`) and the Carrier Scorecard (`view_carrier_scorecard.php`) the same segmented control of links, "Problems" and "Carrier scorecard"; the open screen's segment selected, the other one its link. Nothing else on either screen changed.
- An area strip (`ui/area-tab-strip-centred.md`): `nav.pg-area-tabs.mb-3 > .pg-seg > a.pg-seg-item`, the open one `is-active` with `aria-current="page"`, printed first in `#content` (above the notices and the toolbar or tools row), centred.

## Who sees which segment?
- `pg_delivery_tower_segments($user, $shipping)` in `includes/fn/delivery_tower.php`: "Problems" for the orders right (role under 3 or `manage_ecommerce`, the screen's `validate_ecommerce_access()`); "Carrier scorecard" for the reports right (role under 3 or `manage_ecommerce_reports`, `validate_ecommerce_report_access()`) with commerce and shipping on, as the scorecard refuses otherwise.
- A segment the person may not open is not drawn. With only one left, `pg_delivery_tower_segments_html()` draws no strip, as a lone segment leads nowhere (the Communication strip does the same).

## How is it tested?
- `tests/delivery_tower_test.php`: the segments per right and shipping, the markup, the selected segment, no strip with one segment, both screens printing it first in `#content`.
