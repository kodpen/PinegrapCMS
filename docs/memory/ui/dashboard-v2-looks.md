---
name: dashboard-v2-looks
description: The new dashboard's looks as drawn (pictures 12-14, V4 #8241): card faces, backdrops over the old dashboard's washes, compact density, lights off
---

## How do the looks draw?
- Card faces (flat, neon, glass, aurora) are the drawings' (pictures 12-14), "Look: card style" in `pg_dash.src.css`.
- Backdrops: `#content.pg-dash[data-panel-backdrop]` in "Look: backdrop" draws the drawings' washes (dark: mesh radial cyan/fuchsia/indigo, dusk a vertical #1b1440 to #070b12, ember orange and rose at the foot) over backend.src.css's `#content[data-panel-backdrop]`, which stays the old dashboard's. Light keeps the hues faint.
- Lights off (`pg-dash-calm`) drops the backdrop, shadows, blur and the gauge's glow; compact (`pg-dash-compact`) narrows card padding (12/14 px) and the grid's gap (12 px), never the top row's.
- Why the new dashboard draws its own backdrops: the old dashboard's washes are its own look and must not change (`dashboard-v2-overview.md`); both read the same `data-panel-backdrop`, so the new rules are scoped to `.pg-dash`.

Related: docs/memory/ui/dashboard-v2-side-panel.md
