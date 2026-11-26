---
name: bug-hunt-areas-customer-rhythm
description: Bug hunt of customer rhythm (agent channel request 3034, PR #11767): what was read, the four faults fixed, what was found clean, what was asked and left
module: customer_rhythm
---

## What was the area, and what was read?
- Architect, agent channel 2026-11-26, request 3034: reorder time per product, a customer's own rhythm, stages, the reorder reminder. Hunt PR #11767.
- Read: `includes/fn/customer_rhythm.php`, `customer_rhythm_pass.php`, `customer_rhythm_tell.php`, `customer_rhythm_link.php`, `customer_rhythm_report.php`, `reorder.php`, the privacy erase and merge lines, the counting rule in `customer_values.php`, and the six `tests/customer_rhythm*_test.php`.

## What was fixed?
- An erasure made while a pass was under way was undone: the walk state still held the customer, so the write step put their row back until the next pass. Now a customer none of whose walked orders still carries their key is left out (`pg_customer_rhythm_pass_gone()`).
- A product given back through a refunded return was still a purchase: the customer got a reminder to reorder it and it counted in the product's rhythm. Now the returned units are taken off the line (`pg_customer_rhythm_items_kept()`), in the pass and on the contact card.
- A contact with orders under two addresses (two customer keys) got two reorder reminders the same night: the brake ran per key. Now it runs per contact (`MAX(reminded_at)` by `contact_id`, and once per contact in a batch).
- The contact card showed the store's median as the customer's own interval, without its note, for two orders on one day (no gap). Now own needs first and last order a day apart.

## What was found clean?
- Reminder: written on the row before it is announced, so a run stopped between the two never tells twice; a held-back customer gets `reminded_at` and nothing is announced; off writes no reminder; a contact that is gone gets nothing; consent and İYS are the flow boxes' (decided).
- Cancelled, fully refunded and marketplace orders do not count; a product bought again gets a new due day; a product past the brake's days after its due day is never named.
- Link: HMAC under its own prefix, `hash_equals`, end at most 30 days ahead, ended 410, GET changes nothing, a second POST into the same cart adds nothing, a gone or switched-off product is left out by the cart quote's plan; it names no person, so another customer's link fills only the opener's cart (decided, H9).
- Figures: orders less than a day apart make no gap, so no zero gap; a pair needs the first purchase's units above 0; one order takes the store's median; the curve never rises.
- Pass: keyset paging by `order_date, id`, state renamed whole, lease plus compare-and-swap claim, a redone batch finds `reminded_at` and the stage already written, so nothing is told twice.

## What was asked, and what is left?
- Asked (architect): the panel's Rhythm list and CSV show every name to a user-role reader with the contacts right, while `GET /reports/customer-rhythm` limits a user-role app owner to their contact groups (`pg_contact_owner_sql()`). Same as the Customers Report's top customers, so a product choice, not built.
- Left: an order placed while a pass runs over several ticks is seen only by the next pass, so a reminder can follow a reorder made in that window (minutes). The list's "Own interval" column shows the store's median without a mark, by its column design.

## Which issues were opened?
- None.
