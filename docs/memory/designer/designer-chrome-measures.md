---
name: designer-chrome-measures
description: The Page Design Studio chrome's sizes beside its colour tokens: type, heights, gaps, corners, shadows, focus ring, changed dot, the Options panel's parts in order and each control's states
---

## Which type?
- The identity row's name 14.5 px, weight 640. A section's head and a tab 12.5 px, 600 to 630. A row's label 12 px, 400; a field's value 12 px. A segment 11.5 px, 600. A summary, a path, a hint 11.5 px. A badge 10.5 px, 750. Line height 1.45.

## Which heights and gaps?
- Identity row 60 (padding 13 top, 14 sides, 11 bottom; icon box 36). Tab holder 40 (padding 4, a tab 30, 4 between tabs). Search 34. A section's head 36; sections 8 apart, 12 at the sides.
- A row at least 32, rows 6 apart; the label column 92 (78 under 300 px), then a gap of 10.
- A field and a select 30. A button group 28 with segments of 24. A button 26. A badge 17 to 18. An icon button 28. A switch 32 by 18, knob 12.
- Everything that is clicked has a target of at least 24 by 24.

## Which corners, shadows and movement?
- The panel 14 (0 when it fills a column). A section card 12. The tab holder 11, a tab 8. A field, the search, a group 9 to 10 with a 1 px border. A segment 7, a button 8. A badge 6, a pill 99.
- The selected segment's shadow `--sd-segment-shadow`. Focus: the accent as the border and `--sd-focus-ring`. The changed dot 7 px, the accent, `--sd-dot-ring`.
- A switch and a hover change in 120 ms; nothing moves under `prefers-reduced-motion`.

## Which parts has the Options panel, top to bottom?
- Panel 336 px (300 to 360). Identity 60, tabs 40, search 34, a section's head 36, a row 32, the quick strip 52 (Content only), a closed section 36, "Only what I changed" 38, always at the bottom.
- The dot keeps its place on every row, so labels never shift. A badge is absent at 0.
- A colour, icon or picture picker keeps its own look at height 30 and corner 9.

## Which states has each control?
- Button group: default, hover (a 4 % light layer), selected (`--sd-accent-strong`, white, the shadow), locked (dimmed, no pointer). Up to six values; seven or more are a select. A click on the selected segment removes the value.
- Field and select: focus (accent border and ring); invalid (`--sd-danger` border, one line under it, the value not written). The unit grey at the right.
- Badges: a tab's is filled with the accent on the open tab, `--sd-raised-bg-2` elsewhere; the screen size ("MD", "all") in `--sd-warning` on its soft ground, a click opens the chooser.
- Section card: open has `--sd-raised-bg` and a 1 px line over its body; closed has no ground; its summary a colour square, one value as text, else "none" or "default".
- Architect (2026-11-17, messages 8700, 8733): a section's head carries no icon; a heading's icon box shows its level's glyph; under 300 px a summary that would keep under five letters is left out, the colour square and count stay; the default word is shown whole or not at all.
- Secondary button `--sd-raised-bg-2` with `--sd-border-strong`; primary `--sd-accent-strong`.
