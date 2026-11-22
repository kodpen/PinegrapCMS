---
name: flows-editor-next-screens-2
description: Plan flows-editor-next, in words (boards 1a, 1b, 2a to 2c): data threads only for the box in hand, and how a box panel's fields are written (help, Add value, Recipient chips)
---

## When are data threads drawn? (boards 1a, 1b)
- At rest none, and no row of dots. A box that reads values carries one mark on its bottom edge: 18 px high, a 6 px dot per value in the value's colour, then "2 values".
- A box chosen or pointed at: its threads in and out, the dots they leave on the other boxes with their names, its own chips, and its own unused values as dashed rings at the row's end (names on pointing), so a thread can still be drawn from one.
- Nothing dims. A pointed box's threads go 200 ms after the pointer leaves; a chosen box keeps them.
- A thread's first and last 12 px are upright: it leaves a dot straight down and enters a chip straight up. A dot's name has the ground's colour behind it.
- The strip's Data threads button, pressed, draws every thread as today; it starts not pressed (`pg_wsf_threads` keeps the choice). The run view and the read-only card follow the same rule.

## How are a panel's fields written? (boards 2a to 2c; every box, not only the e-mail)
- Help is one line under a field at most. A longer text opens from a "?" at the right of the field's name, in a small window over the panel; Esc closes it. No paragraph stands open.
- "Add value" is a 24 px button at a value field's bottom right. Its list opens under the field; its first line says what a value is; then the groups of today (`valueGroups()`), and last "Always" with the scheduled words the field fills (`{{date}}`, `{{time}}`). No hint under the field.
- The words an e-mail fills per recipient (`^^name^^` and the others its send path knows) are a group "Recipient" in an e-mail box's list and are drawn as chips; a word typed by hand is drawn as its chip and saved as typed.
- A folded part shows what it holds while closed ("Sender · Organizasyonum").

Related: docs/memory/plans/flows-editor-next.md, docs/memory/plans/flows-editor-next-screens.md, docs/memory/plans/flows-editor-next-screens-4.md
