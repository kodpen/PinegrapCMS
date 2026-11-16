---
name: content-proposals-workspace
description: Waiting content changes asked in the Workspace: when and as whom, who is asked, what a yes or no does, a withdrawn or replaced change taking its request back
module: content_proposals
---

## When is a waiting change asked in the Workspace?
- (PA5, #PRNUM) When a change starts waiting or is replaced (`pg_content_proposals_hold()` → `pg_content_proposals_ask()`), with the Workspace on (`pg_content_proposals_workspace()`: `WORKSPACE_ENABLED`, `ws_enabled()`, `ws_approvals_ready()`) and a channel named on the card ("Content Approval", `pg_content_proposals_settings()['channel_id']`).
- Asked nowhere (`pg_content_proposals_ask_where()`) when the channel is gone, archived or not public/private, the editor may not post there, or no designer can be asked; the bell lines (`content-proposals-bell.md`) still go.
- Opened with `ws_approval_create()` as the editor, rule "any", title "Content change: <page>", text tagging `<#page:ID>` (so `record_type` is `page`). `channel_id` / `message_id` of the `design_proposals` row keep the link (0 when asked nowhere); no column was added (architect, message 5857).

## Who is asked?
- The channel's members of roles 0 and 1 who may post there (`ws_approval_eligible()`), never the editor, ten at most (`pg_content_proposals_approvers_pick()`, `PG_CONTENT_PROPOSALS_ASK_MAX`).

## What does an answer do?
- `ws_approval_settle()` calls `pg_content_proposals_settled()` once, `function_exists()`-guarded, after the request is closed. It finds the change by `message_id` and `channel_id` and reads the closing person's role now (`pg_load_user_row()`); the approvers list is not trusted (`pg_content_proposals_settle_plan()`).
- Yes by a role 0/1: `pg_content_proposals_apply()` in that request, with all its checks (stale, the page lock). A refusal leaves the change waiting or stale and a system line in the channel says "The change to … was not applied: …"; no retry.
- No by a role 0/1: `pg_content_proposals_dismiss()` with that person's answer note as the reason.
- Any other answer (a role 2/3, someone demoted since): noted in the row's `steps.noted` (`user_id`, `decision`, `at`), nothing applied; a system line says only a designer can put it on the site and it still waits in Waiting Content Changes.
- A request taken back or expired, or a change no longer pending, does nothing.

## When is the request taken back?
- A change withdrawn or replaced (`pg_content_proposals_request_closes()`): the old request is settled `withdrawn` as the editor (`pg_content_proposals_request_close()` → `ws_approval_settle()`, not `ws_approval_close()`: the editor may have lost the channel); a replaced change then opens a new one.
- Also when a designer applies, dismisses or meets a stale change on the list screen, so no open request is left whose answer would do nothing.

## Which test guards it?
- `tests/content_proposals_workspace_test.php`: Workspace off, no channel, an archived or gone channel, an editor who cannot post, the approvers, a designer's yes applying, another's yes only noted, a no dismissing, a withdrawn change taking its request back, the wiring.
