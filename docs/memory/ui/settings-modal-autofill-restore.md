---
name: settings-modal-autofill-restore
description: Settings dialog defence against password-manager fills: hold()/review() restore, gesture-clock ownership, patrol() polling, three-restore limit.
---

## Why are unchosen values put back instead of only not counted dirty?
- Identity-filling managers (Kaspersky) ignore `autocomplete` and vendor opt-out attributes (Kuruluş Adresi 2 was filled in the field). Merely "not counting dirty" leaves the value on screen and it enters the database at the NEXT save, silently.
- `hold()` keeps the value of every VISIBLE control when the pane arrives; `review()` writes an ownerless change back to its old value and says `say('filled')` once per pane.
- Hidden controls are excluded: the token, the tag field's hidden input and CodeMirror's textarea are written by this software; restoring them would be fighting ourselves.
- A control that becomes visible later (operator opened a block) is taken into the list at every review with its current value; while closed it could not have been filled.

## Who owns a change?
- Ownership is decided by the clock: a hand gesture within the last 1200 ms. Which control was pressed is ignored, because often it is another control (operator presses "Üret", value appears in an untouched field).
- `pointerdown` and `keydown` are listened on the document in the CAPTURE phase: a card's own dialog lives in the box beside the settings dialog and a file picker elsewhere, yet pressing in both is working on this pane.

## What if the filler fires no event?
- Kaspersky writes the value straight onto the control: no `input`, no `change`, so every event-bound listener sleeps. While the dialog is open `patrol()` polls every 600 ms.
- Polling is one string comparison per visible control; the expensive half (pushing code fields to textarea and serializing the form) runs only on a tick that really found something.
- Polling withdraws while the operator works (gesture within 1200 ms), because `review()` is running then anyway.

## What happens when a manager keeps rewriting a restored field?
- A field restored three times is left alone on screen but not sent: a manager that rewrites as you restore would make it flicker while the dialog is open. After the third restore the value stays on screen and `save()` returns it to its held value right BEFORE serializing. What shows on screen is debatable; what enters the database is not.

Related: docs/memory/ui/settings-modal-autofill-lock.md
