---
name: channel-commands-steps
description: Plan channel-commands, steps CC1 to CC6: the registry with no change of behaviour, the gate and grouped /help, /hatırlat and /özet, /onay /güncelleme /checkin, flows in the / list, the channel's own
---

## Which steps come first?
- CC1 (M) The engine; nothing changes on screen.
  - New: `includes/workspace/command_registry.php` (`ws_command_registry()`, the entries of a group in a function of their own, so two later steps do not write the same lines), `tests/workspace_command_registry_test.php`.
  - Existing: `includes/workspace/commands.php`: the literal list of `ws_commands()`, the switch of `ws_command_run()`, the branches of `ws_command_suggest()`, `ws_command_offered()` and `ws_api_command_kind()` become lookups. A `case` body moves into a runner function unchanged; no `lang()` text is touched.
  - Tests, red first: every key has a runner that exists; the ordered keys equal today's; an application's command named as a built-in is shadowed. `tests/workspace_commands_test.php` and `tests/workspace_command_run_test.php` stay green.
  - Memory: `workspace/workspace-command-registry.md` rewritten for the entry; the census files deleted, what lasts moved there.
  - Cut: CC1a done (#10007, list, names, gate, API class); CC1b done (#10016, runners, live lines).
  - Features: K1 to K3.

- CC2 (M) after CC1: the gate, /help, the list in groups, commands as a reply.
  - CC2a done (#10057): the server side, K4 gate, K5 /help, K7 replies (`workspace/workspace-command-help.md`).
  - CC2b done (#10166): the "/" list in groups and by channel, K4 on the client, K6 (`workspace/workspace-command-help.md`).

- CC3 (M) after CC2: /hatırlat and /özet.
  - CC3 done (#10186): K8 /hatırlat, K9 /özet (`workspace/workspace-command-remind-recap.md`).
  - Tests: `/hatırlat yarın 10:00 Teklifi gönder` gives the plan's day, time and title with the form's default reminder; no time opens the form filled; as a reply the note holds the message's link; the live line; /özet not offered where the button is not; both answers `ephemeral`.
  - Pictures: each command's live line and answer. The PR body names the plan's `[NEW]` changelog line.
  - Features: K8, K9.

- CC4 (M) after CC2: /onay, /güncelleme, /checkin.
  - CC4 done (#10228): K10 /onay, K11 /güncelleme, K12 /checkin (`workspace/workspace-command-approval-update-checkin.md`).
  - Tests: complete, each writes through its button's function with that function's refusals; incomplete, the form opens filled; /güncelleme and /checkin not offered in a discussion; the healths in Turkish and English.
  - Pictures: the three live lines, a form opened filled.
  - Features: K10 to K12.

- CC5 (M) after CC2: flows.
  - CC5 building (`workspace/workspace-command-flow.md`); the flows are read once as the channel opens.
  - Tests: a flow not runnable here not listed; a pick starts through `ws_flow_run_start()` with its rights; a flow's name typed without a pick is an unknown command; none in a discussion or a mailbox room; a customer channel's contact step.
  - Pictures: the list with flows, /akış's argument list, a started flow's line.
  - Features: K13, K14.

- CC6 (M) after CC2: /bildirim, /davet, /ayrıl, /konu, /ara, /bellek. Over the size: CC6a (the first three) and CC6b.
  - CC6a done (#10250): K15 /bildirim, K16 /davet, K17 /ayrıl (`workspace/workspace-command-notify-invite-leave.md`).
  - CC6b done (#10277): K18 /konu, K19 /ara, K20 /bellek (`workspace/workspace-command-topic-search-memory.md`).
  - Tests: each right refused as the menu refuses it; /ayrıl writes nothing before the card's confirm; /bildirim's three words in both languages and without a value; /davet with somebody already in; /ara posts nothing.
  - Pictures: /ayrıl's card, /bildirim's and /davet's answers.
  - Features: K15 to K20.

## What holds for every step?
- Pictures at 1440 and 390, light and dark, to the architect in the agent channel; the PR stays a draft until the look.
- Turkish texts with `php tools/tr_add.php`; no `.min.js` is committed.
