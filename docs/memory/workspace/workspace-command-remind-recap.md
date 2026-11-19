---
name: workspace-command-remind-recap
description: /remind (/hatırlat) and /recap (/özet) in a Workspace channel (CC3 of channel-commands): what each reads, the function it writes through, its gate, live line and writer-only answer
---

## What does /remind (/hatırlat, /hatirlat) do?
- `/hatırlat yarın 10:00 Teklifi gönder`: a plan of the writer's own on My calendar, saved through `ws_personal_plan_save()` as "New plan" saves one: that day, an hour from 10:00, the title, only the writer sees it, a reminder at the start (the `plan_reminder` line, `workspace-personal-plans.md`).
- The reminder is "At the start" (`remind` 0, a stored lead of 0 minutes), also chosen in the form opened without a time; the person may change it there. My calendar's own New plan keeps "No reminder". Architect (2026-11-17, agent channel 8102): K8's "with its reminder" binds.
- Words (`ws_command_remind_data()`, pure): a day word and a time at the start of the line, in either order, read by `ws_parse_date_word()` and `ws_parse_time_word()` as /task reads them; `saat` / `at` before the time is dropped. A time with no day is today's. The rest is the title, as written.
- No time: the New plan form opens filled on the channel's screen: answer `plan_form` (`fill`: title, note, start, end, remind; `strings`: `ws_personal_plan_js_strings()`), drawn by `PGWsMyCalendar.fill()` (workspace_my_calendar.js) from `send()` in workspace.js. Nothing is written until Save; the box empties once it saves. The drawer takes `defaults.remind`.
- Typed as a reply (`parent_id`): the answered message (`ws_command_answered_message()`) gives the note its link (`ws_command_message_link()`, `workspace.php?channel=…&message=…`) and, with no title typed, its line as the title. A message it cannot read: "That message could not be found.".
- Gate `ws_command_gate_remind()`: a team member once `ws_personal_plans_ready()`. The save's own refusals stay (title, day, plan limit).

## What does /hatırlat show and answer?
- Live line `ws_command_live_remind()`: "Will remind you: “title” · Tomorrow 10:00", or "Will open New plan: “title” · day". Warnings: no title, a time gone by.
- Answer `ws_command_remind_answer()`: `ephemeral`, "Reminder set", the line and a link to the plan (`workspace_my_calendar.php?plan=N`).
- API class `ws_api_command_kind_remind()`: `preview` without a time (the form, nothing written), `run` with one. `plan_form` is one of `ws_api_command_preview_types()`.

## What does /recap (/özet, /ozet) do?
- "Sum up what I missed" (`ws_recap_ai()`, briefing.php) as the catch-up line's button asks it, to the writer alone: `ephemeral`, the summary and "Summed up by Pinegrap AI" (`ws_command_recap_html()`).
- `since_id` is the last message read before the visit: `send()` sends `briefing.recap.since_id` with a slash line outside a discussion, as the button does, and `ws_send` passes it on. Without it the writer's read mark is used, which after opening the channel is the newest message, so the answer is "Nothing new was written here since your last visit.".
- Gate `ws_command_gate_recap()`: where Pinegrap AI may read the channel (`ws_ai_channel_state()` available), the button's condition the server knows. The gate gets the channel: `ws_command_offered()` passes it as a third argument to every gate. The refusals are the button's ("Pinegrap AI cannot be asked in this channel.", nothing new).
- Its hint in the "/" list and /help is the button's words, "Sum up what I missed" (architect, 8245). The "/" list: `commandPickerRows(commands, query, inThread, recapHere)` leaves /recap out unless the briefing has `catchup` and `recap` (at least `WS_RECAP_AI_MIN` missed messages at opening), and always in a discussion's box.

## Tests?
- tests/workspace_command_remind_recap_test.php: the words, the plan with the form's reminder, the form without a time, the link in the note, the live line, /recap not offered without Pinegrap AI, both answers ephemeral. The order and live lists in `workspace_command_registry_test.php` and `workspace_commands_test.php`.
