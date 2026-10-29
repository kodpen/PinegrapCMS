---
name: panel-content-contrast
description: Panel and visual editor text and icon contrast (#2530): secondary states on a solid colour not opacity, tones pulled toward the text colour, the editor's --sd-* mixes
---

## What is the target?
- Text that carries information 4.5:1, icons 3:1, in the light and the dark theme. Disabled controls and things hidden on purpose are exempt.
- No global token was retuned for it: token tuning is step B10 of `plans/bootstrap-6-redesign.md`, owner-approved per group.

## How is an "off" or secondary state drawn?
- With the secondary text colour `var(--pg-fg-2)`, never with `opacity`. An opacity of .45–.85 took permission rows (`.pg-perm.is-off`), API scope chips and codes (`api_settings.php`) and the visitor unit (`.pg-vs-unit`) under 3:1.
- Dashboard rows do the same: `pg_widget_row()`'s `muted` (Ecommerce Summary's zero-count periods) and the expired Subscriptions row take `.fg-2`, which beats `.pg-row`'s `color: inherit` because utilities sit in a later layer than `@layer custom`. The Comments card's location line is plain `fg-secondary` (opacity .75 had it at 3.8:1).
- A switched-off permission row keeps the group colour off its icon (`--pg-fg-2` tint) and prints its title in `--pg-fg-2`; its description already was.

## How is a coloured label on a tinted ground made readable?
- Use the theme's emphasis ink: `--pg-success-fg-emphasis`, `--pg-danger-fg-emphasis` (Overdue in the brief, lane counts, the urgent count pill on `--pg-danger-bg-subtle`).
- On a tint too strong for the plain ink (the visitor card's growth pills) or for a user colour, pull it toward the strongest text colour: `color-mix(in srgb, <colour> 80%, var(--pg-fg-emphasis))`. It darkens in light and lightens in dark, so one rule covers both.
- Workspace palette tones (`ws_palette()`) are fills for white text. As a sign or label on the sidebar: group name 60%, group and channel icons 75% of the tone mixed with `--pg-fg-emphasis` (checked for all 20 tones on both themes).
- Dashboard pills printed as `background:<hex>22` over a card (Ecommerce Summary periods, Online Engagement roles, Subscriptions days left) take `color-mix(in srgb, <hex> 50%, var(--pg-fg-emphasis))`: at least 5.6:1 for every tone in both themes; 80% and 60% leave amber and green under 4.5:1. `tests/dashboard_pill_contrast_test.php` computes it.
- A badge on a user colour takes `pg_readable_ink_on()` (PHP) or `readableInk()` (workspace.js).

## Which literal tones were darkened?
- Dashboard card header icons under 3:1 on their pastel card (3, 4, 13, 18, 21, 25, 30) took the next darker shade of the same family; dark card 1 carries `--pg-accent-ink: #18181b` like 27–30.
- Workspace part icons (home, inbox, tasks, notes, scheduled, archived, flows/mcp) reach 3:1 on the active row's tint `#ded6ef`, not only on the sidebar ground.
- `tests/panel_contrast_test.php` computes the ratios from the stylesheet, so a new card or part colour is checked too.

## What about the Key Codes badge?
- `view_key_codes.php` prints `badge fw-light theme-secondary` without `fg-white` since #2863, so it takes the accent ink `--pg-on-accent` on `#90a4ae`: 6.6:1 in both themes.

## How is the visual editor's dark chrome made readable?
- `assets/css/style_designer.css` colours the always-dark editor with its own `--sd-*` tokens; the fixes mix those, no token value changed.
- Soft text (page tabs, the "Legacy" palette badge): `color-mix(in srgb, var(--sd-text-muted) 70%, var(--sd-text))`, 5.6:1 on a lit tab; the muted colour alone was 4.2:1 there. A draft badge inherits the tab's colour.
- Tree id labels: `color-mix(in srgb, var(--sd-accent) 55%, var(--sd-text))`, 5.0:1 on a selected row (was the accent at opacity .7, 2.5:1).
- Publish and every `.sd-icon-btn-primary`: the accent mixed 85% with `--sd-panel-border`, white label 5.0:1 (3.98:1 on the plain accent).
- Lock and link icons: the plain accent without opacity, 3.8:1. "No selection" (`.sd-breadcrumb-empty`) and a switched-off file's name: `--sd-text-muted`, 4.9:1; the "off" badge on `--sd-text`.
- `tests/designer_chrome_contrast_test.php` computes these from the `--sd-*` values and refuses an opacity coming back on them.

## How does the dashboard headline say its direction?
- `pg_widget_headline()` marks its up/down/flat arrow `aria-hidden` and prints a `visually-hidden` "Up from yesterday" / "Down from yesterday" / "Unchanged from yesterday"; the hover title alone never reached a screen reader.
