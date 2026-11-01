---
name: new-dashboard-edit
description: New dashboard plan: edit mode (bar, presets, cancel and save), card tools, drag and keyboard moves, card settings, the gallery, top-section switches, look options, sharing a layout with a role
---

## How is edit mode entered and left? (N5, screens 05-08)
- From "Edit dashboard" in the switch row, a card's "…" button (edit mode with that card's settings open) or the "Add card" tile ending the grid (edit mode, gallery on All). Cancel restores the state taken on entry; Save writes the layout (`dashboard_layout_save`), leaves edit mode and shows the saved chip. Leaving the page unsaved asks first.
- The bar: "You are editing the dashboard" and "Drag a card, or move it with the arrow keys on its grip. Changes are only on your dashboard."; "Preset" segment (Corporate, Blog, Ecommerce, Agency, Simple): a pick replaces cards, shortcuts and tiles with the preset's; "Back to default" restores the preset's cards and clears widths, periods, compares, badges and top-section switches; Cancel; Save.
- Card bodies are dimmed and inert; cards wear a dashed outline; the side panel opens on the right (below the grid under about 1000 px).

## What does a card have in edit mode?
- A grip button in the header ("Move <title>", described by the bar's hint): left or up moves it one place earlier, right or down later, focus staying on it. The card is draggable: the dragged card fades, the target gets a solid outline, the drop puts it before or after by direction. Touch: a short hold first (`ui/dashboard-card-drag.md`).
- A tool row: width Narrow, Medium, Full (one, two or three of the three tracks, dense packing), settings, "Bring forward" and "Move back" (disabled at the ends), remove.
- Settings: "Period" Today, 7 days, 30 days and "Compare" Previous period, Last year, Off, on cards with a period; "Show the 'New' badge"; Done. Outside edit mode the header's period pill cycles the period and saves at once.

## What is the gallery? (N6, screen 09)
- "Add card": search "Search cards: map, flow, order…" in titles and descriptions; category pills All, Recommended (the preset's), General, Content, Visitors, Security, Team, Ecommerce, System; each entry a small sketch of the card, its title, a NEW tag (cards new with this dashboard), RECOMMENDED (recommended, not on the dashboard), the description and "Add" or "On the dashboard · Remove". Only cards the person may see; "No card matches this search." An added card goes last at its default width.

## What else is in the side panel? (N6, N3, N13; screens 06, 10, 11)
- "Top section": switches Since your last sign-in, Shortcuts, Tiles, System Status.
- "Look": Card style (Flat, Neon, Glass, Aurora), Panel backdrop (Auto, None, Mesh, Dusk, Ember; Auto follows the style), Density (Comfortable, Compact), Lights and shadows (On, Off: "Off runs smoother on weak devices"). Per person, the site's Appearance values (`ui/settings-dashboard-card.md`) until chosen. Compact narrows padding and gaps; Off drops shadows, blur and the gauge twinkle.
- "Share with the team", role < 2 only ("Only administrators and designers see this"): "Make this layout the default for" Everyone, Administrator, Designer, Manager, User; "Lock: people in this role cannot change the layout"; "Make default", then the chip "Default for the Manager role · locked". Question 4 decided: the default reaches people with no saved layout; a lock shows everyone in the role the default and keeps their own layouts for when it lifts. A locked person sees no edit button and no card tools; the switch stays.
