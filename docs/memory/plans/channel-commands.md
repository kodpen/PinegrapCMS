---
name: channel-commands
description: Plan (owner 2026-11-16): channel commands grown in steps CC1 to CC12: a registry entry per command, commands for what has a button, records, flows in the / list, application arguments, agents
priority: 6
---

## What is asked?
- Owner (2026-11-16, agent channel message 6671, to the R&D agent): "kanallardaki komutları genişletme ilgili araştırma ve plan hazırla. en son mimara sor planı o hazırlasın."
- The R&D agent's census: `plans/channel-commands-census.md` and its six files. The architect's view on it: agent channel message 6770.

## What did the owner answer? (the R&D agent's polls, 2026-11-16, each the recommended option)
- Poll 93 (message 6777), all five packages of the census into the plan: "Evet, hepsi; sırayla".
- Poll 95 (message 6790), a channel's by-hand flows in the "/" list by name: "Evet; listeden seçilir, Enter "Bir akış başlat" düğmesinin işini yapar". Not chosen: a short command name of a flow's own; the menu alone.
- Poll 96 (message 6792), a command typed for an agent that waits on its request queue becomes a request: "Evet; satır yazanın adıyla kanalda görünür, anmayla aynı frenler".
- Poll 97 (message 6795), one column on `ws_app_commands` for an application command's arguments: "Evet, eklenebilir; sütunu Mimar onaylasın".

## What binds it?
- Architect (2026-11-16, message 6770): the registry is a PHP array in the new `includes/workspace/command_registry.php`, no table and no hook. An entry holds key, names, arguments, group, where it runs, gate, runner, live line and API class; functions are named as strings.
- A new built-in name wins over a name an application took earlier on a running site: the application's row stays, is no longer offered and the API lists it as shadowed.
- A command does what its button does: the same function, rights and refusals. Incomplete, it opens the form its button opens, filled with what was typed, as /poll does. The plan draws no new screen.
- A write to a site record keeps `workspace/workspace-record-commands.md`: the record tagged with `#`, a card with the old value beside the new, only Save writes, the record screen's rights.
- Listing a flow in the "/" list is no keyword start: the decision of 2026-11-05 (#7110) stands, and no typed sentence starts a flow.
- Every command has Turkish and English names, a /help line and texts through `lang()`; an answer meant for the writer is `ephemeral`.

## Whose look is each screen?
- The architect's (owner's agent channel message 2645): the grouped /help and "/" list (CC2), the flows in the list (CC5), each command's live line, answer and card. Pictures at 1440 and 390, light and dark, in the agent channel.

## What is left out?
- Menu only: archive, clear, make private, remove a person, move to a group, apply a template, share with a link, export, the pin; "New sales quote" and "New e-mail" (they open a screen); a direct message; "Start a discussion"; forward.
- /sessiz (/mute): one word with two meanings elsewhere; /bildirim sets the notifications.
- A command that only shows a record (refused, #3801); an order's status by a command (it mails the customer and moves stock, the drawer keeps it); a general "set a field" command (a typed field name is a language trap).
- Counting the use of commands; a flow's own command name; commands in a note, a mailbox room, a scheduled message or a guest's hands; action lines, dice, GIFs; a status text, "do not disturb" for some hours, saving a message, marking unread, a call.
- A saved reply whose body is a command stays: the text comes into the box, the person sees it and sends it.

## Where are the features and the steps?
- Features K1 to K14: `plans/channel-commands-features.md`; K15 to K28: `plans/channel-commands-features-2.md`.
- Steps CC1 to CC6: `plans/channel-commands-steps.md`; CC7 to CC12 and the close-out: `plans/channel-commands-steps-2.md`.
