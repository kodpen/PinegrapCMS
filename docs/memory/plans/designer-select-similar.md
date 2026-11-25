---
name: designer-select-similar
description: Plan (owner 2026-11-24, level 1): "Select similar" in the designer: every element like the chosen one, by kind, classes or component, in a section or the page; steps SE1 to SE3
priority: 7
---

## What is asked?
- Owner (2026-11-24): ten more features, in levels, the designer kept simple and quick (the decision part 108; `designer/designer-light-and-simple.md`). This one: twelve cards changed with one choice, not twelve clicks.

## What is there today?
- Several elements chosen with Shift or Ctrl get one panel, with "— Multiple —" where they differ (`designer/designer-options-multiple.md`). Each is clicked one by one.

## What is built?
- "Select similar" in an element's menu on the canvas and in Overview, and in the command palette; its key is shown in the row.
- It chooses every element of the same kind with the same classes inside the chosen one's section, and says so in one line above the panel: "12 similar · same kind and classes · in this section".
- The two parts of that line are lists. Alike by: same kind and classes (the start), same kind, one of its classes, the same shared component. Where: beside it, in this section, on this page.
- From there on it is the several-elements panel as it is: one change is written to all, one undo step. Ctrl and a click takes one out.
- Nothing is found: the line says "No other element like this one here" and offers the next wider place.

## What must it keep?
- Never chosen: an element the operator may not edit, the inside of a shared component, a system widget's kept parts; the line says how many were left out and why.
- The match is a walk of the page's tree, pure, a few milliseconds on the fixture page; nothing runs until the row is pressed.
- A change across pages is not this: that is a variable (`designer-variables.md`) or Find & Replace.

## Which steps? (S under 400, M about 1,000 product lines; tests red first)
- SE1 (S) The matcher, pure: the four ways of alike, the three places, what is left out and why. Done (#11530): `assets/js/pg_select_similar.js` (`window.PGSelectSimilar.find(tree, target, { by, where, cls, mayEdit, keepsChildren })`), in no bundle yet; SE2 makes it the first source of a part.
- SE2 (M) Done (#11649, `designer/designer-select-similar-part.md`): the menu rows, the line with its two lists, Overview, the command palette, the key. Boards E1 to E4 of `designer/designer-select-similar-drawing.md`; where a line here differs, the drawing wins. The PR body carries a table of the boards: what was built for each, what differs and why.
- SE3 (S) Close-out: memory in `designer/`, this file deleted, the `[NEW]` line named.

## What does every step keep?
- No table or column. The drawing: `designer-drawings-levels.md`. Claim as `[plan:designer-select-similar] <step> — <topic>`.
