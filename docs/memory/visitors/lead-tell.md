---
name: lead-tell
description: What the visitor journey's scores tell (LJ4): customer.hot and customer.returned from the scoring pass, their payload, the flow start boxes, the daily contacts_hot ops alert
module: lead_tell
---

## Which events, and when?
- `customer.hot`: a contact's interest score reaches the threshold (`lead_score.threshold`, default 60) and its row was not hot (or it had none); again only after it fell below and rose (`pg_lead_tell_moves()`).
- `customer.returned`: a contact hot before and still hot starts a visit newer than the row's `last_visit_at`, begun within a day, and `returned_at` is a day old or more; `returned_at` is then set. A contact turning hot by this visit gets only `customer.hot`.
- Named `customer.*`, not the plan's `contact.*`: the API calls a contact a customer (`GET /customers/{id}`), and the area is Customers.

## Where are they raised?
- In `pg_lead_score_contact()` (`includes/fn/lead_score.php`): the old row (`hot`, `last_visit_at`, `returned_at`) is read before the write, then `pg_lead_tell()` announces through `pg_announce()`. Never in a visitor's request; staff are never scored, so never told (J14).
- Contacts already hot when LJ4 landed are not told: their rows already say hot.

## What does the payload carry?
- `pg_lead_event_payload()` (pure): `id`, `score`, `fit`, `threshold`, `reasons` (interest reason codes), `topic` (the strongest interest's name, '' none), `topics` (names), `last_visit_at` (UTC, null). Never a page. Fields and sample in `webhook_payloads.php`.

## How do flows start on them?
- `ws_events_catalog()` once `pg_lead_ready()` and `contact_scores.returned_at`: record the contact, right `contacts`, no filter. Outputs: contact, Interest score, Fit score, Strongest interest, so a condition box tests a range or a topic. Quick setup: the Customers area.

## Who of the staff is told?
- Ops alert `contacts_hot` ("Contacts became hot", no group: listed under Operations alerts for Slack, Teams and Telegram), bell link `view_contacts.php?filter=hot_contacts`, the Hot Contacts view (`visitors/lead-hot-contacts.md`). Sent at the end of a finished scoring pass by `pg_lead_alert_send()`, once a day; names up to three (`pg_lead_alert_detail()`).
- It counts the contacts whose `hot_at` is after `pg_lead_alert_since()`: the last send, the last day when none, two days at most (#11358). Before, a fixed last day left out the contacts that turned hot between a send and the next pass a day later.

Tests: `tests/lead_tell_test.php`, `webhook_event_payloads_test.php`, the parity table's line.
Related: docs/memory/visitors/lead-score.md, docs/memory/platform/ops-alerts.md
