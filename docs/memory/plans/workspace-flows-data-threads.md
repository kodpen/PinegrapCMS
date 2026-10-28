---
name: workspace-flows-data-threads
description: Plan (owner request 2026-10-27, draft being refined): the Flows canvas side of data passing; thin typed threads from a box's bottom into the field they fill, the double-click fork, pure value boxes
---

## What is the picture?
- Control arrows leave a box on the right; data threads leave on the bottom: a thin line (1 px), hanging like a thread (no corners, a slight sag that grows a little with the distance, never overlapping another thread: a small gap stays), coloured by type, each thread a few tones apart from the others, even from the same dot (number, text, yes/no, person, date), no arrowhead. A thread ends on the chip of the field that uses the value, not on the box. Every box shows its kind's icon; every output dot shows its type's icon.
- Threads are drawn from the references kept in the fields (`{{node.<id>.<key>}}`, workspace-flows-data-passing.md); nothing else stores them. Removing a chip removes its thread; the value picker, the API and an AI draft give the same picture.
- A value may skip boxes: a field reads any upstream output, however many boxes back.
- The look in numbers (widths, colours, sag, icons): workspace-flows-visual-spec.md.

## What is the fork?
- Double-clicking an output dot starts one more thread from that dot, never from the middle of another thread; the field chips whose type fits light up; dropping on one binds it. The threads of one output fan out from its dot.
- Equivalents with the same result: long press, right-click "Fork", the keyboard "Connect…", the side panel's "Add value".
- A field that decides where data goes (webhook address, recipients) never lights up and takes no thread.

## What are value boxes?
- New kinds `value` (derived) and `constants` (hand-written rows: name, type, value). Pure: no side effect, no control arrows, only threads; computed once per run when a reader runs, then reused.
- Derived rules come from a short allowlist (compare, join, add, subtract, round, date format, add days, record field); parsed, never evaluated as code.
- Their outputs are declared like any box's (D1), so the picker, the reference check and AI see them.

## How does it stay readable?
- The selected box's threads at full colour, the rest dimmed; a "Data threads" toggle hides them; the outline lists each binding as text (screen reader, phone).
- Problems: a thread from a deleted box red and dashed; a thread from a later box an error; a value from a branch that may be skipped a warning with a "when empty, write" fallback.
- Run view: each box lists the threads it received with their values and the filled field, the template on request.

## Which steps? (after D1-D4 of workspace-flows-data-passing.md)
- T1 threads drawn from references (read only), the toggle, dimming. Done (PR #2515, `workspace/workspace-flows-thread-drawing.md`).

- T2 drag from an output dot to a field chip; the fork by double-click and its equivalents. Done (PR #2540, `workspace/workspace-flows-thread-drawing.md`).

- T3 the constants box, then the derived value box with its allowlist.

- T4 the run view lists threads and values per box.

## Open questions
1. Pure boxes: computed lazily when read (as drawn), or ordinary steps the publisher places on the arrows?
2. The "when empty" fallback: per thread or per field?
3. Does the visual designer's data binding reuse the same chip and thread component (graph-canvas-core.md)?
