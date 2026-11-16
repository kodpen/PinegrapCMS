---
name: quiet-hours
description: Plan (owner 2026-11-15, agent channel poll 54): quiet hours: outside a person's hours a Workspace push waits for the end of the quiet range; steps QH1 and QH2
priority: 6
---

## What is asked?
- The R&D agent's proposal is agent channel message 5354. The owner chose it in poll 54 (message 5366) as it was put to him: outside your working hours no notification comes to the phone, it comes when the quiet range ends; a personal setting; one column (`workflow/decisions-made-workspace-35.md`, `workflow/decisions-made-schema-22.md`).
- Today: a channel's "all / mentions / none" choice (`ws_channel_set_notify()`); the push queue takes a delay and drops a row whose inbox line was read (`pg_push_enqueue()`, `ws_push_still_relevant()`); a person's working day is minutes and weekdays with no start hour; "I am away" covers e-mail only (`workspace/mail-away.md`). Nothing looks at the clock.

## Which steps are there?
- QH1 (M) The record and the rule. Done: #9844 (`workspace/workspace-push-delay.md`).
  - A migration step adds `ws_profiles.quiet_hours` TEXT NULL with the idempotent helper, a JSON as `mail_away`: `start` and `end` (HH:MM), `off_days` (0 or 1). A site whose update has not run works as today.
  - One pure function with tests: for a setting, the person's time zone (the one the person card's local time reads, `workspace/person-card-facts.md`), their working weekdays and a moment, is it quiet and how many seconds are left. A range over midnight, a day the clock changes, equal start and end (off), a day off with `off_days`.
  - Where a Workspace push is queued for a person, a quiet moment gives the delay to the range's end, for a browser and a team device alike. The inbox line, the badge and the mail notices do not change; a line read meanwhile is dropped by the queue as today.
- QH2 (S) after QH1: the setting. A "Quiet hours" part where the person sets their own Workspace notifications: From, To, "Also quiet on the days I do not work", Clear; saved by the person's own session only.
  - Close-out in the same PR: `workspace/quiet-hours.md`, this plan deleted.

## What is left out?
- A site-wide default a manager gives; a kind that breaks through the quiet range (an approval request to the person, a direct message); quiet hours for mail notices. Each is a later yes of the owner.

## Whose look?
- The architect's: a settings part whose behaviour this plan decided, as AF1's field was. QH2 posts its pictures (unset and set; 1440 and 390, light and dark) in the agent channel and is not marked ready before the look.
