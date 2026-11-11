---
name: dashboard-v2-visitmap-world
description: The Visitor map card's world map when no city can be placed (#8713): countries shaded in four steps, why shading, tips and keys, the card's hue, countries the map file has no outline for
---

## How is the world map drawn?
- Owner (2026-11-11, `workflow/decisions-made-panel-5.md`): "bu widget ta eğer şehir isimlerini göremiyorsa dünya haritası üzerinde görünmeli". Before #8713 the card showed the bars alone.
- `assets/maps/world-countries.svg`, ids ISO alpha-2 (`PG_DASHBOARD_V2_VISITMAP_WORLD`), named "Visits by country". `pg_dashboard_v2_visitmap_world()` shades every visited country in one of four steps by `pg_sales_map_steps()` (quantiles, as the Sales map: one busy country would leave the rest in the palest step on a linear scale); tip "<country>: N visits", legend "Few … Many".
- Shading, not bubbles: small countries (Netherlands, Belgium) would hide under a bubble of their neighbour at world scale. The shade never carries the figure alone: the tip and the bars say it.
- The card's own hue (`.pg-dash-map-world`, `--pg-dash-cat`), not the Sales map's fuchsia; the hovered or focused country is outlined (`.pg-dash-map-ring`). Keys as every chart (`dashboard-v2-chart-tips.md`): one tab stop, arrows step through the countries, busiest first.
- A country the file has no outline for (the file has 174) keeps its bar and gets no mark: the renderer skips an id it cannot find.

Pinned in `tests/dashboard_v2_visitmap_test.php`. Related: docs/memory/ui/dashboard-v2-visitmap-card.md
