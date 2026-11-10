---
name: dashboard-v2-status-card-look
description: New dashboard System Status card's look (2026-11-10): dash palette, score in the arc's sweep, light by card style, lights off keeps the twinkle, only failed checks in sight, Changes button
---

## Which colours does the card's gauge take?
- The `'dash'` palette of `pg_health_gauge_band()` (`dashboard-system-status-gauge.md`): the dashboard's own family, so the arc, the name in the greeting, the charts and the chips are one set of hues.
  - 50 to 79: cyan, indigo, purple, fuchsia, the dashboard's sweep.
  - 80 and up stay cool and lead with teal, from 90 with the emerald of an OK chip.
  - Under 50 leads with the amber of a warning chip, under 25 with orange into the red of a problem chip.
- Light stops are the 600 weights: each clears 3:1 on a white card; the 700s read as one dark bar there.
- The score is written in the same four stops (`background-clip: text` on `.pg-dash .pg-dash-status .pg-health-score`, inline-block so the sweep spans the digits); forced colours get `CanvasText`. Widget 2 keeps its palette and its flat ink.

## How does the gauge follow the card style?
- Rules under "Look: the gauge by card style" in `pg_dash.src.css`, on `.pg-dash[data-widget-theme]`. Only the light around the bar and the groove change; the squares, their twinkle and the colours are the same in all four.
  - Flat: no bloom and no glow layer; the halo at .34 on a dark card.
  - Neon: glow .68 and bloom .62 on a dark card, glow .32 on a light one. The bloom is raised more than the glow: the glow sits under the squares, and a strong one washes their twinkle out.
  - Glass: glow .44, bloom .26 and a pale groove (white at .1; on a light card slate at .1).
  - Aurora: the layers as widget 2 draws them.
- Each strength is the rest value; its breath is literal keyframes `pg-dash-gauge-<layer>-<style>` inside `prefers-reduced-motion: no-preference` (`system-status-gauge-layers.md`).

## What does "Lights and shadows: Off" do to the gauge?
- It hides the bloom and glow layers in every card style. The squares keep twinkling: the switch drops what it names. Until #8448 it also held the twinkle, which read as a gauge whose animation was gone.
- The twinkle stops only for `prefers-reduced-motion` and for an off-screen dial.

## Which checks are always in sight?
- Only the failed ones, as chips (`pg_dashboard_v2_status_chips()`). Every other check folds behind one dashed chip: "+n more" after failed chips, "n checks" when it stands alone, then "· n warnings" and the warn tint when a warning is inside. Until #8448 the first eight chips showed.
- Chips are 0.6875rem. The dial is as wide as the card (18rem), centred between the heading and the chips, which sit at the foot of a card stretched to the greeting's height.
- The header button reads "Changes" and opens the Release Notes dialog.

## How is it checked?
- `tests/dashboard_v2_status_test.php` pins the rules and the chips. No unit test sees the look: it was compared in a browser in the four styles, light and dark, lights on and off.
- Decisions: `workflow/decisions-made-product-41.md`.
