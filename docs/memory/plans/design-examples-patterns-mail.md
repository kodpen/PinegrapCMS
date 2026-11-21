---
name: design-examples-patterns-mail
description: Step DX2b of the Design examples plan: the parts of the approved e-mail settings drawing as patterns (tabs with a state, choice cards, provider sign-in, effects confirm), before EM1
---

## What did the owner ask?
- Owner (2026-11-21, agent channel message 11316), on the e-mail settings drawing: "bu tasarımlar modern ve kaliteli olmuş buradaki bileşen öğeleri ve kart tipi design_examples.php ye girsin" (`workflow/decisions-made-panel-14.md`).

## Which step is it and when does it run?
- DX2b (M) of `plans/design-examples-patterns.md`: after DX2 (#10885) has landed and before DX3, the one step open on `includes/fn/look_examples.php` and `design_examples.php` at that time.
- EM1 of the one e-mail settings plan waits for DX2b: the EM steps copy these patterns and write no recipe of their own.
- The source is the drawing `plans/mail-settings-one/email-settings.html` (boards A to E), in words `plans/mail-settings-one-design.md` and `plans/mail-settings-one-design-box.md`.

## Which patterns does it add to the Patterns group?
- "Tabs with a state" (board A): the taller segmented control as one modifier of `.pg-seg` (34 px items, the label strong), each item its label and a state pill in the three tones; at 390 px the pill is a dot with the word as `title`.
- "Value rows" (board A): one inner card, a row a thing: tile, name, one sentence saying what happens today, "Change" quiet small at the row's end; one meta line under the card.
- "Choice cards" (boards B, D1, D2): DX2's `.pg-start` cards as a radio group, two a row and one under 576 px: the chosen card's ring and radio, a card with a note at its end ("about 10 minutes"), and under the group a label with only the chosen card's fields.
- "Step line" (boards D1 to D3): a number and a name a step, the current step, a finished step with the green check; under 576 px the other steps' names are hidden. Where DX2's sheet draws the line already, only the missing states are added.
- "Provider sign-in" (board D2): the centred inner card with Google's and Microsoft's own buttons, exactly the values of the box note, and the one line under them; the same in the light and the dark pane.
- "Result line" (board D3): the green line after a sign-in or a test, two lines of text at most.
- "Confirm with its effects" (board E): the dialog with the rose tile, title and sub line, the list of effects (a green check stays, a rose cross goes, a blue info line), Cancel quiet and the danger button.
- "Switch rows" (the Features board, `plans/features-switchboard-design.md`): a card of rows with a tile, name, one line and a switch at the end; the off row with "Settings", the state pill with "Open", the row not allowed.

## What binds it?
- The recipes go into `backend.src.css` under `pg-` names of the part, never of a screen; no new pill tone, button rank or colour, but for the two provider buttons' brand values.
- Each pattern is drawn live in both panes with its classes under it and has its test in `tests/look_examples_test.php`, as the others have; the phone pane (DX4) takes the choice cards and the step line.
- Rendered at 1440 and 390 px beside the drawing before ready; the PR's "Screen check" names each pattern.
- The two buttons' brand rules are read on the providers' pages by this step; a difference from the box note is asked of the architect.

Related: docs/memory/plans/design-examples-patterns.md, docs/memory/plans/mail-settings-one-design.md, docs/memory/plans/mail-settings-one-design-box.md
