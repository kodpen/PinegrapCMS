---
name: workspace-approval-decide-checks
description: What ws_approval_decide_check() refuses before a team member's answer to an approval request, shared by the panel, the external API and its dry run
---

# Answering an approval request: the checks

## Where are the checks?

`ws_approval_decide_check()` (includes/workspace/approvals.php). The panel
action `ws_approval_decide`, `ws_approval_decide()` itself and
`POST /workspace/approvals/{id}/decide` (with its dry run) all go through it,
so a refusal is the same everywhere. A guest's answer by link has its own
checks (`ws_approval_guest_decide()`).

## What does it refuse?

An unknown decision; a closed request; a request whose deadline passed
(`expired` true, the caller settles it); a person not asked or who already
answered; a deleted request message; a request message in an earlier
version of the conversation (`ws_message_in_past()`, eras.php); a person who
may no longer write in the channel. The UPDATE in `ws_approval_decide()`
still matches only `decision = 'pending'`, so a double press counts once.

## Why is the earlier-version check here and not only in the panel?

The panel's action switch refused it before the call, but the external API
did not: an app could still answer, and so settle, a request the channel
keeps frozen after "Clear conversation". The check moved into the shared
function (test: `tests/approval_decide_past_test.php`).
