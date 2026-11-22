---
name: flows-editor-next-screens-3
description: Plan flows-editor-next, the new functions in words (boards C1, 3, D1 to D3): the whole-flow trial, a live flow's counts, the Split box, sending hours, leaving the flow
---

## How is a whole flow tried? (board C1)
- "Try" in the bar, for who may try a box today (the owner editing it). The bar shows "Trial · nothing is sent" and "End the trial"; the canvas is read-only meanwhile.
- The panel: the record (a contact search as Run's; for a start about an order or a form entry, that record; the last five the flow's runs were about first), then the steps in order, each with what the box would do in its Try tab's words.
- Reading boxes read (a record, table rows, a condition); a box that sends, writes or calls does nothing and gives the values it would give, empty where only a real send knows them; a Wait is passed; a box that waits for a person (task, approval, check-in) takes its first outcome, and its row offers the others.
- "Next step" walks one box, "To the end" the rest. On the canvas: a tick on a walked box, "passed" on a Wait, the outcome on a condition, "Next" on the box in turn, taken arrows green, the others faint. "Preview" shows a message as that person would get it.
- Nothing is stored: the script sends the record and the outcomes chosen so far, the server walks the draft and answers the steps.

## How are a live flow's counts shown? (board 3 of the first sheet)
- "Counts" in the bar, for who may read the flow's runs, on a flow with a live version; beside it the days (7, 30, 90; 30 at first).
- Over each box, small pills: runs waiting at it now (amber), passed in those days (plain), failed (rose); a box no run reached shows none. A start shows "N started".
- A pill is a button: the panel lists those runs (the record's name, what it waits for or when it passed), 20 at a time, a row opening the run; "Open all in Flow runs" under them.
- Read when pressed and every 60 s while shown; counted over the live version's box ids, older versions' runs included where the id is the same.

## What is the Split box? (board D1)
- Kind `split`, group Logic, label "Split": two to five arms, each a name (A, B, …) and a share in whole percent, adding up to 100; each arm an outcome with its own arrow.
- A run takes one arm at random by the shares; the arm is the step's outcome, so a retry and the run view show the same one. In a trial the first arm, the row offering the others.

## What are sending hours? (board D2)
- A folded row "Sending hours" in the settings of Post a message, Send an e-mail, Send an SMS and Send a WhatsApp message: off at first. On: the days (Monday to Friday at first), from and to (09:00 to 20:00), in the site's time zone, and "Outside the hours": "Send at the next hour that fits" (first) or "Do not send".
- A box reached outside its hours waits as a Wait box does (`wake_at`) and sends when they open; with "Do not send" it settles done with `sent` 0 and says why, as a person without consent does.
- An e-mail to a contact group starts its campaign inside the hours by the same rule.

## How does a run leave the flow? (board D3)
- "Leaving the flow" in the name's window: rules "If this happens, the run ends", each one event that names a person or a record (the events the start list offers, read from the catalogue); two at most are drawn, ten the limit.
- A run about a record (`subject_type`, `subject_id`) ends when such an event arrives for the same contact or record: open steps are cancelled, the run's status is `left`, its last line says which rule ended it. Runs without a record never leave.
- Rules are part of the graph (`leave`), so they are published with a version and a run keeps its version's rules. The window says how many runs left in the last 30 days.
- "Left" is a run state of its own in the runs list, the run view and the API, neither completed nor failed; the owner is not told as for a failure.

Related: docs/memory/plans/flows-editor-next.md, docs/memory/plans/flows-editor-next-screens-2.md, docs/memory/plans/flows-editor-next-steps-2.md
