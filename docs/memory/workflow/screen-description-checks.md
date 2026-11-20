---
name: screen-description-checks
description: What a screen description of a redrawn screen must hold so the result matches it: every part the screen has today placed, countable lines a unit test pins, who looks on dev after landing
---

## What happened?
- Owner (2026-11-20, on Claude's row of Site Settings › Assistants and agents): "planı çizim ile birlikte eklediğin halde yaptığı ekrana bak claude ekranı en az 4 modal ekran boyu kaydırma sonra bitiyor. çizimindeki modernizim ve pratiklik tamamen atlanmış".
- Found: the plan had a drawing and one sentence on the row. The steps moved what the sentence named (the guide) and left every part the sentence did not name where it was; both PRs said "not seen on dev". Nobody set the result beside the drawing.

## What does a description of a redrawn screen hold?
- Every part the screen has today, by its English label, with its new place: stays (where, in which order), moves (to which fold or drawer), goes. The architect reads the screen's code for the list; a drawing alone is not a description.
- "What must be true?": lines that can be counted in the markup (how many fields, buttons and columns in view, the longest help text, what sits in a fold or a drawer), so a unit test pins each.
- What does not change: field names, button names and values, ids other code reads.

## What does the step's worker do?
- A part of the screen the description does not place is asked of the architect (`architect-asking.md`); it is never left where it was by default.
- Each "must be true" line gets its test, named in the PR body beside the line.
- A drawing is followed for what it shows: a part drawn with four fields is built with four fields, the rest placed by the description.

## Who looks at the result?
- Nobody sends pictures to the owner or the architect (`screen-described-in-plan.md`). After a redrawn screen lands, the architect asks the test coordinator to open it on dev at 1440 and 390 px and to check the description's "seen on dev" line; a miss is a `bug` issue naming the line.

Related: docs/memory/workflow/screen-described-in-plan.md, docs/memory/ui/screen-composition-rules.md, docs/memory/workflow/test-coordinator.md
