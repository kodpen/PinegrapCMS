---
name: accessibility-view-rules
description: What binds the designer's Accessibility view (View menu switch, Tab order, Headings, Names, "See as"): why it exists, what it may never do, which file holds each part; landed in AV1 to AV4
---

## Why is there an Accessibility view?
- Owner (2026-11-24): one of ten designer features in levels (level 2), kept simple and quick (`workflow/decisions-made-product-108.md`, `designer-light-and-simple.md`); accessibility is seen on the page, not read from a list.
- The audit (`editor-audit-panel.md`) lists contrast, target size and tree faults; the view shows what it cannot: the order a keyboard walks, what a screen reader calls a region or a picture, and how the page looks without a colour.

## What must it keep?
- The marks live in the editor's own layer over the canvas, are worked out when a switch goes on and when the hand rests, and never enter the tree, the Preview or the saved page.
- Order and names are worked out as a browser does (tab index, hidden and off elements, labels) by one pure reader with its tests (`accessibility-reader.md`); where it cannot know (a script moves focus) it says nothing.
- The sight filters are style only: no picture is read or changed (`accessibility-view-see-as.md`).
- It finds no fault the audit does not know: one list of faults stays, the audit's (`pg_a11y_check_html()`); a marked fault shows the audit's own sentence.
- No table or column; one View menu row, no bar button, a part loaded on first use, nothing running while off.

## Which file holds what?
- The reader `PGA11yReader` (AV1 #11529): `accessibility-reader.md`.
- The View menu switch, the card, Tab order, Headings and Names marks, a fault's card (AV2 #11657): `accessibility-view.md`.
- "See as" (AV3 #11685): `accessibility-view-see-as.md`.
- The drawing, which wins where it and a text differ: `designer-accessibility-view-drawing.md` (V1, V2, V6) and `designer-accessibility-view-marks-drawing.md` (V3 to V5).

## How is a new kind of mark added?
- A new list comes from the reader first, pure and tested against the audit's counts per code; then a fourth row on the card and its marks in `.sd-av-layer` of the part `accessview`. A fault the audit does not raise is added to the audit first.
