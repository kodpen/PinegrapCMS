---
name: workspace-flows-send-hours
description: Sending hours of a flow's sending boxes (post, email, sms, whatsapp; FE13, #11446): days and hours in the site's zone, a wait through wake_at or "not sent", Try and trial say when, form row, AI hint
---

## What is the setting? (`includes/workspace/flow_send_hours.php`)
- `hours` on Post a message, Send an e-mail, Send an SMS and Send a WhatsApp message (`ws_flow_hours_kinds()`), added in `ws_step_config_normalize()` by `ws_flow_hours_config()`; always present, so the AI catalogue lists it as an object.
- `{on, days, from, to, outside}` (`ws_flow_hours_normalize()`): off at first; days ISO 1 Monday to 7 Sunday, Monday to Friday when not given; from/to HH:MM, 09:00 and 20:00 by default; outside `wait` (default) or `skip`.
- Publish problems (`ws_flow_hours_problems()`, in `ws_flow_graph_check()` beside the retry problems): on with no day; from not before to. Hours over midnight are not offered.

## When are they open?
- `ws_flow_hours_next($hours, $now, $zone)` (pure, `tests/workspace_flow_send_hours_test.php`): `$now` when off or inside (from inclusive, to exclusive), else the start of the next listed day's hours, looked at over eight days in the zone ('' = the site's, PHP's default); 0 when they can never open.

## What does a run do outside them?
- `ws_flow_hours_hold()` runs at the top of `ws_step_run()`, after the approved MCP/change branches and before the box writes that it began, so nothing is sent.
- `skip`: the box settles done/done with `sent` 0 and "Outside its sending hours, so nothing was sent.", as a person without consent does.
- `wait`: one conditional write to waiting, `wait_key` `hours:<until>`, `wake_at` = until, result "Outside its sending hours: sends at …"; the claim's `attempt` and the run's `boxes_run` are given back, so the send that follows is its first try and retries count as before.
- `ws_flow_step_recheck()` hands an `hours:` key to `ws_flow_retry_recheck($step, $until)`, which makes the box ready when the moment comes; the claim then sends.
- Hours that never open (a graph published before the check) fail the box (the failed arrow when drawn).
- An e-mail to a contact group goes through the same hold, so its campaign starts inside the hours.
- Run events: a waiting box's `for` is `hours` with `until` (`ws_flow_run_waiting_for()`).

## What do Try and the trial say?
- `ws_flow_hours_try()` on the done answers of a sending box in `ws_flow_box_try()` (the last return and the contact-group return): outside the hours it adds "…it would send at …" and `next` = that moment, or "…so it would not send" for skip. The trial reads the same answer.

## Where is it drawn and told?
- Canvas: `hoursFields()` in `workspace_flow.js`, after the retry fields, for `FL.steps.hours_kinds`: a folded row (`wsf-fold`) "Sending hours" showing Off or 09:00–20:00; inside, "Send only within these hours", the days (as the condition's weekdays), Starts at / Ends at through `dateInput('time')`, "Outside the hours" (Send at the next hour that fits / Do not send).
- Pinegrap AI: the `hours` hint in `$any` of `ws_flow_ai_catalogue_hints()` (flow_draft.php), left out unless asked.
