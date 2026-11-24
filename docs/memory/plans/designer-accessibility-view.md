---
name: designer-accessibility-view
description: Plan (owner 2026-11-24, level 2): the canvas shown as a keyboard, a screen reader and a colour-blind eye meet it: tab order, headings, names, sight filters; steps AV1 to AV4
priority: 6
---

## What is asked?
- Owner (2026-11-24): ten more features, in levels, the designer kept simple and quick (the decision part 108; `designer/designer-light-and-simple.md`). This one: accessibility seen on the page, not read from a list.

## What is there today?
- The audit lists contrast, target size and tree faults such as a skipped heading level (`designer/editor-audit-panel.md`). The order a keyboard walks, what a screen reader calls a region or a picture, and how the page looks without a colour are seen nowhere.

## What is built?
- "Accessibility view" as a switch of the canvas bar's View menu. On: a small card floats in a corner of the canvas with four switches; one is on at the start, Tab order.
- Tab order: a number on every element the keyboard reaches, in the order it reaches them, joined by a thin line; a step that jumps backwards on the page is marked.
- Headings: each heading carries its level as a tag; a skipped level is marked, and the card lists the outline.
- Names: regions are outlined with their role and name; a picture shows its text, or "no text"; a control with no name is marked.
- See as: the canvas through a filter, one of red-weak, green-weak, blue-weak, no colour, blurred. It changes nothing but the look of the canvas.
- A mark's click chooses the element. A marked fault shows the audit's own sentence and its "Fix" where the audit has one.

## What must it keep?
- The marks live in the canvas's own layer, are worked out when a switch goes on and when the hand rests, and never enter the tree or the Preview.
- The order and the names are worked out as a browser does (tab index, hidden and off elements, labels), by one pure reader with its tests; where it cannot know (a script moves focus) it says nothing.
- The filters are style only: no picture is read or changed.
- It finds no fault the audit does not know: one list of faults stays, the audit's.

## Which steps? (S under 400, M about 1,000 product lines; tests red first)
- AV1 (M) The reader, pure: the keyboard's order over a tree, the heading outline, regions and their names, pictures' texts. In progress.
- AV2 (M) after AV1 and DD16: the card and the three kinds of marks.
- AV3 (S) after AV2: the sight filters.
- AV4 (S) Close-out: memory in `designer/`, this file deleted, the `[NEW]` line named.

## What does every step keep?
- No table or column. The drawing: `designer-drawings-levels.md`. Claim as `[plan:designer-accessibility-view] <step> — <topic>`.
