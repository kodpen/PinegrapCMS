---
name: dashboard-v2-width
description: How wide the new dashboard gets and how many columns its grid takes: the 118rem cap, columns by the dashboard's own width (container queries), edit mode, the fallback
---

## How wide does the new dashboard get?
- `main#content.pg-dash` stays full width, so the panel backdrop (`#content[data-panel-backdrop]`, painted on main) fills the screen. Its direct children (switch row, edit bar, top, grid, edit mode's work area) stop at 118rem (1888 px), centred: `.pg-dash > *` in `assets/css/pg_dash.src.css`.
- Why: on a 2560 px screen three columns made cards of 760 px; five columns kept the narrow cards but stretched the greeting, the pipeline and every wide card across the screen. The owner's design stopped at 1440 px with three columns; four columns of about 454 px keep the drawings' card size on a wide screen (#6359).

## How many columns does the grid take?
- `.pg-dash` is a query container (`container: pgdash / inline-size`). `@container pgdash` gives 2 columns from 38rem, 3 from 58rem, 4 from 94rem of the dashboard's content width (window minus rail minus #content's 2 × 16 px). With the 60 px "Minik" rail that is a 640, 1000 and 1600 px window, as the old window rules were; the cap starts at a 1980 px window. M spans two only from two columns; L spans the row.
- The rail's view changes the width the rule sees: at a 1600 px window the open rail (250 px) gives 3 columns of 423 px, where the window rule gave 4 of about 310.
- The System Status card stands beside the greeting from 58rem and the greeting's tiles go four across from 38rem, by the same container.
- Edit mode keeps the page's columns although the side panel takes 23rem of the work area: the container is `.pg-dash`, not `.pg-dash-work-main`, so what is arranged is what the page shows (picture 06 draws three columns beside the panel).
- A browser without container queries uses the old window rules inside `@supports not (container-type: inline-size)`.
- `container-type: inline-size` makes no containing block for a fixed child (checked in Chromium: a fixed child of main stays at the window's left edge); the figure box sits in `<body>` anyway.
- `tests/dashboard_v2_look_test.php` checks the cap, the container and that no window-width rule sets the grid, tiles or top outside the fallback.
