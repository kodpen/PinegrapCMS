---
name: mail-away
description: "I am away" in My e-mail settings (IB12): ws_profiles.mail_away (11.0.211), the text once per sender, the stand-in for conversations given to an away person, the team plan
---

## What is it?
- Owner (2026-11-14, poll 3363, `workflow/decisions-made-workspace-29.md`): a person says they are away for a range of days, with a text and an optional stand-in. Built by IB12 (#9591).
- Kept in `ws_profiles.mail_away` TEXT NULL (step 11.0.211, `upgrade_2026_11_0_ws_mail_away()`), a JSON `start`, `end` (Y-m-d), `text`, `stand_in` (user id, 0 none), `plan_id`. Schema: owner 2026-11-15, channel decision 3500, shape 3505, in the decisions list (schema part 21). Readiness `ws_mail_away_ready()`; read by `ws_mail_away()`, checked by `ws_mail_away_input()` (pure), written by `ws_mail_away_save()`.

## Where is it set?
- The "I am away" part of the "My e-mail settings" drawer (`awayPart()` in `workspace_mail.js`, under the signature): First day, Last day, Answer (at most `WS_MAIL_AUTO_REPLY_MAX`, 5,000 characters), Stand-in (Nobody or a team member, never the person), Clear. Drawn only once the column is there (`away_ready`).
- Same actions as the signature: `ws_mail_my_settings` returns `away` (`ws_mail_away_offer()`: null once the range is over), `people`, `away_max`; `ws_mail_my_settings_save` checks the signature first, then keeps the away fields (`away_start`, `away_end`, `away_text`, `away_stand_in`; error field `away_<field>`).
- No first day switches it off. A range at most 366 days (`WS_MAIL_AWAY_DAYS`), its last day not past. No API route or MCP tool.

## When does a sender get the text?
- `ws_mail_ingest()` calls `ws_mail_away_reply()` for a mail in an ongoing conversation (the box's own automatic answer stays for a new one), after the loop gate, the block list and the hourly hold let it in.
- Due (`ws_mail_away_reply_due()`): the holder (`assignee_id`) is away today in their time zone (`user.timezone`, else the site's; `ws_mail_away_today()`), the mail's `auth` is not `fail`, and this sender has not had the same text from this box since the first day began (an out item, `user_id` 0, same `to_list` and `body_text`). A changed text can go once more.
- Sent to the mail's sender by `ws_mail_auto_reply_send()` (mail_status.php, shared with the box's automatic answer): the box's route, queued, `Auto-Submitted`, kept as an "Automatic answer" item under the card. Not a first answer; the status stays open. A demonstration site sends nothing.

## Who holds a conversation given to an away person?
- `ws_mail_away_stand_in($user_id, $channel)` gives the stand-in while the person is away today and the stand-in is a member who may answer there (`ws_mail_can_answer()`), else the person. `ws_mail_conversation_check()` asks it when somebody gives a conversation to another person (panel and API); taking it oneself stays. The stand-in is told (`mail_assigned`).
- IB11's "Give it to" rule calls the same function.
- At ingest nothing moves: the holder keeps an ongoing conversation and its `mail_customer` notice.

## What stands on My calendar?
- Saving writes an all-day plan "Away" with visibility `team` through `ws_personal_plan_save()` (its id in `plan_id`; updated in place, or made again when it was deleted on the calendar). Clearing deletes it through `ws_personal_plan_delete()` while the range is not over; a past range's plan stays.

## Tests?
- `tests/workspace_mail_away_test.php`: input, decode, the range, the day in a zone, due, hand-to, the step and the wiring. The ingest's send and the holder change against a database were not run.
