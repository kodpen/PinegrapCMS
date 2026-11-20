---
name: screen-composition-rules
description: How a settings pane, a form drawer and a connect row are put together in look A so a person understands them: folded rows, one line of help, tabs, a remove button, one state everywhere
---

## Why is this here?
- Look A's recipes say how a part looks; these lines say how a screen is put together from them. Owner (2026-11-20), of a mailbox's drawer: "bu ayar çorba gibi bir ekran olmuş. epostayı kaldıracak düğme falan yok" (`workflow/screen-described-in-plan.md`).
- Architect (2026-11-20): gathered from the drawings the owner said yes to (`workflow/decisions-made-panel-12.md`, `decisions-made-panel-4.md`) and his words on earlier screens. A plan's screen description keeps them (`workflow/screen-described-in-plan.md`); the live patterns come with `plans/design-examples-patterns.md`.

## How is a settings pane laid out?
- A purpose line, then the parts as folded rows: icon tile, name, one line of state in the person's words, a state pill, a chevron; one row open at a time. Never several open cards one under another.
- A list of things (mailboxes, agents, servers) is list rows with each thing's state and actions. In a Site Settings pane the dialog's Save is the one filled button, so "Add …" is quiet with a plus; on a list screen of its own "Add …" is the filled one.
- An opened row holding fields ends above the dialog's foot: one column, what is rarely changed in one fold, a long list or a guide in the part's drawer (`workflow/screen-description-checks.md`).

## How is a form in a drawer or dialog laid out?
- By what the person came to do: more than about eight fields are split into tabs (the segmented control) or groups under a group label; what few people change sits folded under "Advanced".
- A new thing starts from ready starting points (cards with an icon and one line), not from an empty form of every field.
- Steps a person follows somewhere else (another service's console) are numbered steps in their own drawer, opened by one button, never paragraphs inside the form.
- The foot: one filled button (Save), Cancel quiet. Whatever can be added can be removed there: `pg-button-danger` ("Remove", "Disconnect") with a confirm that says what goes with it.

## How much help is written?
- One line a field or part, saying what the person gets, in plain words with no inner term (owner, 2026-11-15: "kademe kısmı benim tarafımdan bile ne olduğu anlaşılmıyor"). More goes behind "How does this work?".
- A paragraph that explains the same thing stands once on a screen (owner, 2026-11-20: "kopya içerik gibi sürekli").

## How is a state shown?
- In the same words on every screen that shows the thing: an account connected on Connections reads connected wherever it is named, or the line says what is still missing and leads to it.
- "Not connected" is followed by the button that connects.
- The pill's tone follows the state, and the word changes with it: never connected "Not connected" grey; waiting for the person (a sign-in, a renewal) says what it waits for, amber; tried and failed "Failed" rose; working "Connected" or "Ready" teal.

## What stays the parts' own rule?
- Buttons, tabs, fields, pills and their ranks: `look-new-parts.md`, `look-marketplace-ranks.md`. Widths: `look-page-widths.md`.

Related: docs/memory/ui/look-new-parts.md, docs/memory/ui/look-design-examples.md, docs/memory/ui/look-overview.md
