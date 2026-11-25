---
name: architect-draws-new-part
description: Working rule (2026-11-21): a part with no model on the Design examples screen is drawn by the architect before its job is given; words alone gave the timeline strip the owner sent back
---

## What happened?
- The conversation timeline's rework (zoom, days, width) was given out in words (`decisions-made-workspace-44.md`). Built as #11027 it kept every measure of the words and still read as noise: pills with the same space around each, the zoom buttons on a line of their own.
- Owner (2026-11-21, in the architect's window): "çizmeden işi verdiğin için saçma sapan oldu ben çizersin diye güvenerek sana vermiştim."

## What is the rule?
- A part of its own kind (a strip, a ruler, a chart, a card with no model on `design_examples.php`) is drawn by the architect before the job goes out.
- The drawing is an HTML sheet on the look's tokens (`ui/look-sheet/sheet.css`) in `<folder>/<topic>-drawing/` beside its memory, rendered dark and light as two pictures.
- Its boards: today's state for the difference, each state of the part (as it opens, pointed at, at its two ends, narrow, on a phone), the sizes three times enlarged, the wheel and the keys.
- The architect looks at its own rendering before the job goes out: a sheet nobody looked at is not a drawing. A picture the owner asks to see is posted in the agent channel, not shown in the architect's window (`architect.md`).
- The plan says the same in words (`<plan>-screens.md`); where the two differ the drawing wins, and what neither says the worker asks of the architect.

## What does it not bring back?
- No approval round: the job does not wait for the owner's yes, and no worker sends a screenshot to be approved (`screen-described-in-plan.md`). His word on the picture, when it comes, corrects the drawing and the plan.
- A screen put together from parts the Design examples screen has is still described in words only.

## Is it an owner decision?
- His words are the reason; the rule is the architect's working rule, changed by a PR that gives its reason.

Related: docs/memory/workflow/screen-described-in-plan.md, docs/memory/workflow/architect.md, docs/memory/workflow/decisions-made-workspace-46.md
