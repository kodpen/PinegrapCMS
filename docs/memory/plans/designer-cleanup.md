---
name: designer-cleanup
description: Plan (owner 2026-11-24, level 2): "Clean up" for a design: style rules, files, shared components and variables nothing uses, listed with the reason and removed only when ticked; steps CU1 to CU4
priority: 6
---

## What is asked?
- Owner (2026-11-24): ten more features, in levels, the designer kept simple and quick (the decision part 108; `designer/designer-light-and-simple.md`). This one: a design that has been worked on for months is made light again without fear.

## What is there today?
- A shared component and a file each say where they are used. Style rules nobody uses pile up in a design's stylesheets; the publish check plan's weight measure (PC2) will count them and remove none.

## What is built?
- "Clean up" as a row of the command palette and as the button of the publish check's finding of unused rules. A window with four lists, each with a count and the size it would free.
- Style rules of the design's own stylesheets that no page, component or e-mail design of it uses. Files of the design nothing refers to. Shared components placed nowhere. Variables used nowhere.
- A row: its name, one line of why it is thought unused, "Show". Nothing is ticked at the start; "Remove 14" removes what is ticked.
- Before removing, a snapshot of the design's stylesheets and trees is taken and named "Before clean-up" with the day; the closing line says where to bring it back. Files and components leave the way their own screens remove them.

## What must it never do?
- Count as unused a class that a script, an interaction, a condition, a classic page, another design's page or a system widget's default layout names; a class written by a setting or a record's value; a rule under a comment that says to keep it. When it cannot tell, the row is not listed.
- Remove anything by itself, on a schedule or at Publish.
- Touch a stylesheet that is not the design's own (a library's, the look's).

## Which steps? (S under 400, M about 1,000 product lines; tests red first)
- CU1 (M) The finder, pure: the classes in use gathered from trees, components, interactions, conditions and the words in scripts; a stylesheet's rules matched against them; the "cannot tell" cases. Done #11560 (`designer/design-cleanup-finder.md`).
- CU2 (M) after CU1: the window with its four lists, "Show", what opens it and its states; boards U1 to U4 of the drawing. The PR body carries a table of the boards: what was built for each, what differs and why. Done #11668 (`designer/designer-cleanup-window.md`).
- CU3 (S) after CU2: removal with the snapshot and its closing line; the foot of board U3, the closing line and the row that stayed of U4. The PR body carries a table of the boards: what was built for each, what differs and why.
- CU4 (S) Close-out: memory in `designer/`, this file deleted, the `[NEW]` line named.

## What does every step keep?
- No table or column. The drawing: `designer/designer-cleanup-drawing.md` and `designer-cleanup-states-drawing.md`; it decides where the two differ (`workflow/screen-follows-drawing.md`). Claim as `[plan:designer-cleanup] <step> — <topic>`.
