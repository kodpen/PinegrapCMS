---
name: panel-content-contrast
description: Panel text and icon contrast (#2530): off and secondary states on --pg-fg-2 not opacity, palette tones pulled toward the text colour, dashboard card icons at 3:1, what is still open
---

## What is the target?
- Text that carries information 4.5:1, icons 3:1, in the light and the dark theme. Disabled controls and things hidden on purpose are exempt.
- No global token was retuned for it: token tuning is step B10 of `plans/bootstrap-6-redesign.md`, owner-approved per group.

## How is an "off" or secondary state drawn?
- With the secondary text colour `var(--pg-fg-2)`, never with `opacity`. An opacity of .45–.85 took permission rows (`.pg-perm.is-off`), API scope chips and codes (`api_settings.php`) and the visitor unit (`.pg-vs-unit`) under 3:1.
- A switched-off permission row keeps the group colour off its icon (`--pg-fg-2` tint) and prints its title in `--pg-fg-2`; its description already was.

## How is a coloured label on a tinted ground made readable?
- Use the theme's emphasis ink: `--pg-success-fg-emphasis`, `--pg-danger-fg-emphasis` (Overdue in the brief, lane counts, the urgent count pill on `--pg-danger-bg-subtle`).
- On a tint too strong for the plain ink (the visitor card's growth pills) or for a user colour, pull it toward the strongest text colour: `color-mix(in srgb, <colour> 80%, var(--pg-fg-emphasis))`. It darkens in light and lightens in dark, so one rule covers both.
- Workspace palette tones (`ws_palette()`) are fills for white text. As a sign or label on the sidebar: group name 60%, group and channel icons 75% of the tone mixed with `--pg-fg-emphasis` (checked for all 20 tones on both themes).
- A badge on a user colour takes `pg_readable_ink_on()` (PHP) or `readableInk()` (workspace.js).

## Which literal tones were darkened?
- Dashboard card header icons under 3:1 on their pastel card (3, 4, 13, 18, 21, 25, 30) took the next darker shade of the same family; dark card 1 carries `--pg-accent-ink: #18181b` like 27–30.
- Workspace part icons (home, inbox, tasks, notes, scheduled, archived, flows/mcp) reach 3:1 on the active row's tint `#ded6ef`, not only on the sidebar ground.
- `tests/panel_contrast_test.php` computes the ratios from the stylesheet, so a new card or part colour is checked too.

## What is still open?
- The Key Codes badge (`view_key_codes.php`) and the visual editor's right panel (`add_system_style.php`, style_designer): later PRs.
