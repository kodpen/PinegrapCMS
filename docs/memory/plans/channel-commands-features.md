---
name: channel-commands-features
description: Plan channel-commands, features K1 to K14: the registry, the gate that knows the channel, grouped /help and / list, commands as a reply, /hatırlat, /özet, /onay, /güncelleme, /checkin, /akış
---

## The engine (CC1)
- (K1) One entry per command in `ws_command_registry()`. `ws_commands()`, `ws_command_names()`, `ws_command_offered()`, `ws_command_run()`, `ws_command_suggest()` and `ws_api_command_kind()` read it. No name, order, answer, right or text of today's 22 commands changes.
- (K2) No name without a runner: a test walks every key and calls nothing that is missing. Today such a name falls into /help without a word (the census's first finding).
- (K3) A built-in name wins over an application's: the application's row is kept, never offered in the list, and the API's list of that application's commands marks it `shadowed`.

## The gate and /help (CC2)
- (K4) The gate knows the channel: an entry says where it runs (a channel, a discussion, public or private only), and what cannot run here is not offered here, in the "/" list, /help and the palette. Today /agent-channel is listed in a discussion and refused only when run.
- (K5) /help in groups, each under its header: Tasks, Plan, The channel, Records, Agents and applications. `/help <command>` (`/yardım görev`) shows that command alone: its names, arguments and an example. Both to the writer alone.
- (K6) The "/" list in the same groups once it is longer than it shows: a header row that cannot be picked; typing filters across the groups as today, and with a word typed the headers go.
- (K7) A reply reaches every runner. Typed as a reply and without a text, /task makes the task from that message (what "Make a task of it" does), /decision and /note mark that message (`ws_message_mark()`). With a text they do as today.

## The person's own (CC3)
- (K8) /hatırlat (/remind) `<when> <text>`: a personal plan with its reminder (`ws_personal_plan_save()`), as "New plan" on My calendar writes one. The day and time words are the ones /task reads (`workspace/workspace-command-live-line.md`). Typed as a reply, the plan's note carries the message's link. The live line says what will be reminded and when; the answer is the writer's alone. No time: the form opens filled.
- (K9) /özet (/recap): "Sum up what I missed" (`ws_recap_ai()`), to the writer alone. Offered only where the catch-up line's button is; its refusals are the button's.

## What the box's Insert menu has (CC4)
- (K10) /onay (/approval) `<title> @people`: `ws_approval_create()` after `ws_approval_input()`. Nobody named, or no title: the form opens filled.
- (K11) /güncelleme (/update) `<health> <text>`: `ws_status_post()`; the healths of `ws_status_healths()` are offered as values in the screen's language. Not offered in a discussion.
- (K12) /checkin `<question>`: `ws_checkin_start()`. Not offered in a discussion.

## Flows (CC5)
- (K13) /akış (/flow): its argument list is the by-hand flows `ws_flows_runnable_in()` offers in this channel; the picked one starts through `ws_flow_run_start()`, with the contact step a customer channel's start has. Public and private channels only.
- (K14) The same flows are listed by name in the "/" list under their own header, after the built-in commands. A picked row and Enter do what "Start a flow" does. A flow is found only by picking: a typed word that happens to be a flow's name is an unknown command, answered as today.
