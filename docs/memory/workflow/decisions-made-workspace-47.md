---
name: decisions-made-workspace-47
description: Decisions made, workspace part 47 (2026-11-22 on): the Flows editor is redrawn and widened as drawn (canvas edge to edge, add at the arrow, whole-flow trial, counts, Split, sending hours, leaving)
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-workspace-46.md`.

## Is the Flows editor redrawn and widened? (owner, 2026-11-22, in the architect's window)
- Owner, with a picture of the editor (a start, a Wait and an e-mail box, the e-mail's panel open): "Mimar akışlar sayfasında nasıl mantıklı bir geliştirme yapabiliriz?"
- Told three proposals in words (calm data threads, a shorter box panel, a live flow's counts): "çiz önermelerini". Shown them drawn: "daha da modernize etsek ve özellik olarak da genişletip, kullanımı kolaylaştırsak?"
- Shown the second sheet (the canvas from edge to edge with boxes as cards, adding at the arrow, several boxes with frames and notes, a trial of the whole flow, a box that splits, sending hours, leaving the flow): "görseller ve detaylı planla projeye ekle".
- So both sheets are to be built: `workspace/flows-editor-drawing/editor.html` and `threads-panel-counts.html`, by the plan `plans/flows-editor-next.md`.

## What does this change of the flow window of 2026-11-12?
- `decisions-made-workspace-20.md` ("Is the flow window rearranged?") keeps its jobs: the left part only adds boxes, the right part is only the chosen box. They stop being fixed columns: the left is a rail and "+" on the arrows, the right a card over the canvas that is there only while something is chosen. The architect told the owner this before his last words above.
- Until the plan's steps land, the columns are what is on screen.

## What did the architect add, not the owner?
- Every function of today's window stays; under 768 px today's forms stay.
- No schema: frames and leave rules live in the graph's JSON, counts read the run tables as they are, a run that left gets the status `left`.
- The trial: reading boxes read, nothing is sent or written, a Wait is passed, a box waiting for a person takes its first outcome and offers the others; only who may try a box today.
- "Often next" in the add list is a fixed table, not counted use.
- Split: two to five arms, whole percents adding up to 100, the arm kept as the step's outcome.
- Sending hours: on Post a message, Send an e-mail, Send an SMS and Send a WhatsApp message; off at first; in the site's time zone; outside them the box waits for the next hour that fits, or does not send, as chosen.
- Leaving: rules on the flow, each one event naming a person or a record; only a run about a record leaves; "left" is a state of its own, and the owner is not told as for a failure.
- Left out: a vertical layout, opens and clicks of an e-mail on the canvas, a split's winner picked by the system.
- The owner was told these in the same window when the plan was written.

Related: docs/memory/workflow/decisions-made.md, docs/memory/workflow/decisions-made-workspace-46.md, docs/memory/workflow/decisions-made-workspace-20.md, docs/memory/plans/flows-editor-next.md, docs/memory/workspace/workspace-flows-decisions.md
