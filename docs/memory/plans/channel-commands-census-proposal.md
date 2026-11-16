---
name: channel-commands-census-proposal
description: The R&D agent's proposal from the channel commands census (2026-11-16) for the architect's plan: five packages (engine, buttons, records, applications and agents, the team's own), order, left out
---

## Whose is this?
- The R&D agent's, not a plan and not a decision: the architect writes the plan (`channel-commands-census.md`). The names are suggestions.

## 1. The engine first (nothing new on screen)
- One entry per command holds its names, arguments, gate, runner, live line and API class: a new command is one entry and its function. Today's commands move over unchanged, held by the tests.
- The gate learns the channel: what cannot run here is not offered here.
- /help in groups and `/help <command>`; the picker in groups once the list is longer than it shows.
- A reply reaches every runner: /task, /decision and /note typed as a reply act on that message.
- The findings of `channel-commands-census-findings.md` are fixed.

## 2. Commands for what has a button (no schema)
- /hatırlat (/remind): a personal plan with its reminder, `/hatırlat yarın 10:00 Teklifi gönder`; as a reply the note carries the message's link.
- /onay (/approval): the approval request, people named with @; incomplete it opens the form filled, as /poll does.
- /akış (/flow): starts a by-hand flow this channel offers, picked from its popup.
- /özet (/recap): "Sum up what I missed", to the writer alone.
- /bildirim (/notify) and /sessiz (/mute): the writer's notifications of this channel.
- /davet (/invite) @people, /ayrıl (/leave) with a confirmation, /konu (/topic), /ara (/search: the palette on this channel), /mesaj (/msg) @person (the direct message, as the palette opens it), /tartışma (/discuss) as a reply, /bellek (/memory) for a memory entry, /eposta (/mail) opening the form, a status update and a check-in (names to choose: /status is the task's).

## 3. Records (no schema: the catalogue has the writers)
- /create learns the kinds named on 2026-10-29: contact first, then ERP account, calendar event, workflow table row. Each through the create card and "All fields".
- /kargo (/tracking) `#order number`: a tracking number through the old-and-new card and Save; the card says the customer is not mailed.
- To decide: an order's status by a command, and one general "set a field" command over the drawer's kinds, of which /price and /stock would be two cases.

## 4. Applications and agents
- An answer for the writer alone, sent by the application for that one command: every other chat has it.
- Typed arguments in the registry's own shape, so the popup and the strip work and the event carries the values (only Discord has this). One column on `ws_app_commands`: schema, the owner's yes.
- An agent's command: typed for an application that waits on its request queue it makes a request, the command and its values in the brief, under the doors' brakes.

## 5. The team's own commands (no code)
- The by-hand flows a channel offers are listed by name in the / picker, as Slack lists a team's workflows; Enter starts the picked one as "Start a flow" does. No new kind of start.
- A flow may also carry a short command name of its own, and later typed values. The owner's decision: on 2026-11-05 a keyword start was "not added until asked" for fear of accidental starts; a typed command is never part of a sentence.

## In which order?
- 1, then /hatırlat, /onay, /akış and /özet of 2, then 5 and the agent's command of 4 once the owner has decided, then 3, then the rest of 2 and 4.

## What is left out?
- Record-card commands (refused, #3801). Action lines, shrug, dice, GIFs: no work is done with them.
- A status text of the person, "do not disturb" for some hours, saving a message, marking unread, a call from the channel: features to ask first.
- Archive, clear, make private and removing a person by typing: they keep their dialogs on the menu.
- Commands in a note, a mailbox room, a scheduled message, a guest's hands.
- Counting the use of commands: the architect's, it needs a place to keep the counts.
