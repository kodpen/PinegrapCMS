---
name: more-features-tables
description: The fourteen tables the eight plans of plans/more-features.md counted, weighed against what exists (owner, poll 84), part 1: the digest, the meeting link, the map, customer value, referral
priority: 6
---

## Why is each table weighed again?
- Owner (2026-11-16, agent channel poll 84, message 6513), asked whether the fourteen tables may be added: "mimara soralım bunu yazılımdaki bir özelliği geliştirererek yapabiliyormuyuz değerlendirsin."
- Per table below: what exists that could hold it, what bends, the R&D agent's view. The architect decides; the owner is asked again with its view. Part 2: `plans/more-features-tables-2.md`.

## Digest: marks of flow and record (1)?
- There is: a start's `ws_scheduled_actions.last_run_at`, read by the count "New form submissions since the last run" (`forms_new`, `includes/workspace/scheduled.php`); a start is claimed twice (`running_at`; `ws_flow_runs` unique on flow and start key).
- Without a table: the records box reads "since the start's last run", the list twin of that count. A drawn flow's step is handed 0 today (`ws_step_action_row()`): code, no schema.
- Bends: a record shown later with an older date is missed; the box reads the list's publish date field where it has one.
- View: no table.

## Meeting link: links (1) and a booking's meeting address?
- There is: `booking_staff`, one row per user with `service_ids` and `enabled`; `booking_slots.php` narrows to one staff row; `ws_profiles`, one row per user.
- Without a table: the link's code is worked out from the staff row and the site's secret; the link offers the member's own services while they can be booked. The meeting address is one column on `ws_profiles`, read when shown.
- Bends: a link cannot be switched off or renewed by itself, nor offer fewer services than the member has.
- View: no table, one column.

## Map: coordinates of a record (1)?
- There is: no coordinate anywhere, and the address providers' calls drop them; a collection has number fields; server code can write a record's field (`ws_change_write()`).
- Without a table: latitude and longitude are two number fields of the collection, named in the block; filled by hand, by import, or by "Find from the address" on the record's form and as a bulk action: the server asks once, at save. No job pass, no hash, no state.
- Bends: an edited address keeps its old coordinates until the action is used again. Gain: a pin is corrected by hand and travels with export and the API.
- View: no table.

## Customer value: a row per customer, and month rows (2)?
- There is: rule-filled contact groups already judge `order_count`, `order_total` and `last_order` (`pg_audience_conditions()`); a settings text holds 64 KB of JSON with a claim (`pg_config_text_claim()`); `orders` is indexed on the billing e-mail and the user.
- Without tables: the general job works out the figures (tiles, the cohort table, the groups' sizes, the top customers) into one JSON summary the screen reads; a cell hands its bounds to the existing rules.
- Bends: each rebuild groups all orders by customer, heavy on a very large shop (the pass keeps its place in a temp file, as the audience job does); no list of every customer.
- View: no table in the first version; one only if a measured rebuild is too slow.

## Referral: referrals (1) and a new kind of store credit?
- There is: `commissions` (the code as text, the order, an amount, pending to paid), written at completion, voided on cancel and restored (`pg_order_cancel_commissions()`, `pg_order_restore_commissions()`); store credit is a `gift_cards` row whose `kind` is an ENUM.
- Without a table: a referral is a commission row under the customer's code, its amount the reward; once payable the credit is issued and the row marked paid. The kind: a new ENUM value, or `manual` with the order in its reason.
- Bends: commissions are labelled by hand today; these would pay themselves. A refund changes no commission today: the take-back is new code either way.
- View: no table; the new kind as the one schema step.
