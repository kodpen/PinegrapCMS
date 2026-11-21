---
name: screen-composition-nesting
description: Frames and field sizes inside a settings pane's opened row: no card in a card, one frame at most, one field height, unit suffixes, selects that show their value; ask the architect first
---

## Why is this here?
- Owner (2026-11-21, agent channel message 11675), of the settings panes moved to folded rows: "ayar kartları içinde kart içinde kart ve iç içe girmiş görünümler hatalı olmuş", "bir yeri düzenlerken 2. kez 3. kez geri dönmek çok pahalı o yüzden bilmediğin birşeyi mimara sor".
- A scan of 19 panes at 1440 and 390 px the same day found the same faults on many of them (`plans/settings-scan-findings.md`). These lines add to `screen-composition-rules.md`; a step that moves a pane keeps them and its PR's Screen check says each was looked at.

## How many frames may stand inside an opened row?
- The row is the frame. Fields, switches and help lines stand straight on its surface with the row's own padding, never against its edge and never in a card of their own.
- One frame at most inside it, and only for a list: list rows or "Value rows" (`plans/design-examples-patterns-mail.md`). Nothing framed stands inside that frame.
- Groups of fields are parted by a group label and space, or one hairline; not by a tinted box, a grey card holding a white card, or a second border.
- A form that adds a thing (a service, a template, a member) is not an open card inside the row: a quiet "Add …" opens its drawer.
- A card moved into a row loses its frame, heading and inner-card look by the fold's own style (`settings-fold-rows.md`); a pane never writes its own override for that.

## Which sizes do fields keep?
- One height for every field of a pane: the look's 32 px. A page or image picker, a tag field and a file field are that height closed; the old "Browse…" button is not drawn.
- A unit ("minutes", "days", "%") is the field's suffix in the field's own text size and weight, after the number; never bigger than the number, never before it.
- A select shows its chosen value whole: it takes a width class that fits its longest option (`settings-card-field-widths.md`) or the full cell. Every select and list is the look's own, never the browser's bare one.
- Labels of one pane share one size and weight; a part's heading inside a row is a group label, not a page heading.
- Warning and note boxes use the help text size.
- A list or table that is wider than a phone becomes list rows under 576 px; nothing scrolls sideways at 390 px.

## What is asked before the work, not after?
- A worker or planner who cannot tell from Design examples, these lines and the plan how a part is drawn asks the architect in the agent channel before building it (`workflow/architect-asking.md`): the part, the two ways it sees, the one it would take. One question is cheaper than a second visit to the screen.
- What needs no question: which of the look's parts to copy when the plan names it.

Related: docs/memory/ui/screen-composition-rules.md, docs/memory/ui/settings-fold-rows.md, docs/memory/ui/settings-card-field-widths.md, docs/memory/workflow/screen-description-checks.md
