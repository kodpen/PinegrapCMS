---
name: agents-card-settings
description: Plan (owner 2026-11-15): the Site Settings dialog keeps its place after a save; the agents' settings say what each door is for and are redrawn in the panel's look; AS1 to AS5
---

## What is asked?
- Owner (2026-11-15, in the architect's window), on Site Settings › "Assistants and agents": "buluttaki oturumda dürtme çalışmıyor bunu teyit ettik. senden ricam, bunu düzgünce anlatan bir yapı ile ayarlar ekranlarında iyileştirmeler." His other words: `workflow/decisions-made-panel-9.md`.
- Seen today: after a save the pane is read again and `paint()` (`assets/js/backend.src.js`) removes the pane's drawers and sets the form's scroll to 0, so the agent's drawer closes and the pane jumps to its top.
- In an agent's row the report line comes before "Edit", so on a row with a report the button wraps to the next line.
- An agent on the poll door still shows its last push error: only a successful knock or the Claude card's save clears `door_error`.

## What is known about the doors?
- A push knock starts a new run at the address it is given; it never wakes a session that is already open. A cloud session therefore takes the poll door and waits on its queue (`workspace/agent-door-routine-double.md`, `workflow/agent-channel-wake.md`).

## Which steps are there?
- AS1 (S) The dialog keeps its place after a save: the form's scroll stays, the drawer the save came from opens again on the pane that was read again, and "saved" is one message in the dialog. Errors and warnings as today; after "Delete", and after a new record's first save, the drawer closes. For every pane of the dialog. The message's place is a new look: pictures to the owner.
- AS2 (S) The door in plain words: the "Door" field as two described choices ("It waits on its queue": a session that stays open, a cloud session too; "The site knocks at an address": an agent that starts work on each knock, it never wakes an open session), the door's state as one sentence in the drawer, `door_error` cleared when the door's kind, address or token changes and shown only for a push door. Tests for the clearing. After #9615 (it changes `agents_settings.php`).
- AS3 (M) The agents' rows and drawer in the panel's look: the row recipe of "Agents in this channel" (PF7, #9615): picture, name, handle, role pill, state pill, one summary line, "Edit" always at the row's end. In the drawer: sections, fields by the look's recipe, "Save", "Try" and "Delete" in a fixed foot. The facts missing today, from PF2's reader: reported model, usage windows, waiting requests. After AS2. A new design: pictures to the owner.
- AS4 (M) The pane's other cards (Claude, Pinegrap AI, MCP servers) by the same recipe.
- AS5 (S) Close-out: `workspace/workspace-agent-settings.md` and `ui/settings-modal-endpoint.md` rewritten as what is now, this plan deleted.

## What does every step keep?
- No schema change. The field names (`ws_agent[<id>][<field>]`), the named submit buttons and what a save stores stay.
- AS1 can start now (`backend.src.js`, `includes/settings/modal.php`); AS2 after #9615, AS3 after AS2, AS4 after AS3.
- Pictures: light 1440 and 390, one dark, a close-up of the changed part; one short question to the owner per step.
- Claim as `[plan:agents-card-settings] <step> — <topic>`.
