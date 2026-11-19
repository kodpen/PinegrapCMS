---
name: workspace-command-approval-update-checkin
description: /approval (/onay), /update (/güncelleme) and /checkin (CC4, #10228): what each reads, the function it writes through, gate, where it runs, live line, the form opened filled
---

## What does /approval (/onay) do?
- `/onay Son logo @ayse @mehmet`: an approval request through `ws_approval_create()` (its checks in `ws_approval_input()`), as the box's Insert menu "Ask for approval" posts one: the people asked, any one of them decides (`rule` any), no deadline, no agents or customers. The answer is `done`: its message and card in the channel.
- Words (`ws_command_approval_data()`, pure): on the first line the people tagged `<@user:N>` or typed `@username` (a team member's exact username, read as /task reads them, `ws_command_typed_assignees()`); the rest of that line is the title. Lines under it are the details, as written; a tag there only tells. `ws_command_approval_read()` reads the team only when a username is typed.
- No title, or nobody named: answer `approval_form` (`fill`: title, text, approvers), drawn by `PGWsApprovals.form(view, h, fill, done)` (workspace_approvals.js) with the title, details and people chosen; nothing is written until its Send. Runs in a discussion too, as the menu item.
- Live line `ws_command_live_approval()`: "Will ask for approval: “title” · names", warnings from `ws_approval_input()`; or "Will open Ask for approval: …" with what is missing; a typed username nobody has.

## What does /update (/güncelleme, /guncelleme) do?
- `/güncelleme riskte Tedarikçi gecikti`: a status update through `ws_status_post()`, as the "Status update" form posts one. The state first, then the text (may be empty, as the form allows).
- States (`ws_command_health_word()`): yolunda, riskte, riskli, gecikti, gecikmede; on-track, at-risk, off-track (also without the hyphen, or as two words "at risk"). The argument popup offers `ws_command_suggestions()['healths']` (`ws_command_health_values()`): yolunda, riskte, gecikti on a Turkish screen, on-track, at-risk, off-track on any other, with no label repeating the word. Argument kind `health`, source `healths`.
- No state: answer `status_form` (`fill`: health '', text), drawn by `PGWsStatus.form(view, h, fill, done)` (workspace_status.js): the figures as always, the typed text in place of the draft and its help only the tags sentence (`st_text_help_own`), the suggested state chosen.
- Live line: "Will post a status update: At risk · “text”" with `ws_status_post_check()`'s refusals, or "Will open Status update: …".

## What does /checkin do?
- `/checkin Bu hafta önünüzde ne engel var?`: `ws_checkin_start()` asks it, as "Ask for a check-in" does. Names /checkin and /check-in.
- No question: answer `checkin_form` (`fill`: the usual question, `ws_checkin_default_question()`), drawn by `PGWsStatus.checkinForm(view, h, fill, done)`.
- Live line: "Will ask for a check-in: “question”" with `ws_checkin_start_check()`'s refusals.

## Where are they offered?
- Group The channel (`ws_command_registry_channel()`, orders 136 to 138, after /recap). /update and /checkin are `where` channel: never in a discussion, as the menu hides their items there (`ws_status_thread_refusal()`).
- Gates: `ws_command_gate_approval()` (`ws_approvals_ready()`), `ws_command_gate_status()` (`ws_status_ready()`), the conditions of the menu items. The rest (post right, a closed channel) is the writing function's own refusal; the commands add no rule.
- In `send()` (workspace.js) the three form answers go through one map to their opener; `done` empties the box once the form is sent. API classes `ws_api_command_kind_approval()`, `_update()`, `_checkin()`: `preview` when the form would open; the three types are in `ws_api_command_preview_types()`.

## Tests?
- tests/workspace_command_approval_update_checkin_test.php: names, gate by discussion, the states in both languages, the approval words, the forms opened filled, the API classes, the writing calls (read from the source: running them needs a database).
