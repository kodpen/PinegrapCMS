---
name: delivery-problems-desk
description: Orders › Delivery problems (view_delivery_problems.php, DT3): the list of shipment_problems, its filters and bulk bar, a problem's drawer, assign, note, close with a reason, the audit lines
module: delivery_problems
---

## Where is it and who opens it?
- Orders menu › "Delivery problems" (after Returns), `view_delivery_problems.php`; the work is `includes/fn/delivery_problems.php`. Gate `validate_ecommerce_access()` (Manager and up, or the store right), as the orders list.
- The rows come from `shipment_problems`, written by the hourly pass (`includes/fn/delivery_tower_passes.php`). Before the table exists the screen says it comes with the update.

## How is the list put together?
- Above the toolbar, the link segments "Problems" / "Carrier scorecard" shared with the scorecard (`delivery-tower-segments.md`).
- A printed list on the list table driver (`delivery_problems` in `pg_list_table_printed_lists()`), at most 2,000 rows (`PG_DELIVERY_PROBLEMS_LIST`): open ones oldest found first, closed ones newest closed first.
- Columns: Parcel (opens the drawer), Order (link; a marketplace order carries a grey "Marketplace" pill), Carrier, Kind pill, Since (days since found, to the closing for a closed one), Assignee, the note's first words.
- Kind pills: Stuck and Not handed over amber, Delivery problem and Returned rose; grey once closed.
- Filters in the toolbar: Open / Closed / All as link segments; kind and carrier as `pg_filter_select()`; a quiet "Settings" at the right (`delivery-problems-settings.md`). No fill: nothing is added here.
- With rows chosen the bar offers "Assign to…" and "Close…", each a drawer whose fields belong to the list's form (`ids_field` `problems[]`). Closed rows among the chosen are skipped.
- Empty, unfiltered: "No open delivery problems" and one line on what the pass watches (Design examples' empty state).

## How is a problem's drawer laid out?
- `?problem=<id>` opens `dialog#delivery_problem`, built as the Design examples "Drawer". Head: parcel number, kind pill, days.
- `dl.pg-kv`: order, the billing name (as the order screen shows it), carrier, shipped on, days in transit with "Usual days" (the nightly summary's median for the carrier and province, else country) as its grey line, the estimate at checkout.
- "What happened": gathered rows, newest first, then the carrier's tracking page (`pg_shipment_view()`). The parcel row keeps only its latest status, so the rows are: label made, shipped, the latest status, delivered, and each problem found and closed on the parcel. A full status history would need a new table.
- Assignee (people with the orders right) and Note (one line, 500 characters). Foot: "Close the problem" quiet and Save fill. "Close the problem" shows the reason as a segmented choice (Sent again, Refunded, Found, Other) with its note and swaps Save for its own "Close" fill. No danger button: a problem is never deleted.
- A closed problem shows assignee and note as text and "Closed: <reason> · date" with Close only. The pass's reason reads "Delivered", "Scanned by the carrier", "The order was cancelled" or "The parcel was removed".

## What does a save write?
- `pg_delivery_problem_changes()` (pure) gives the columns; a closing note is added after the note with " · ". The write has `closed_at = 0` in its WHERE, so a problem the pass closed meanwhile is not written over.
- Each changed problem is one `audit_log` line, type `shipment_problem`, action `update` or `close`, label the parcel number; fields kind, assignee, note, closing only, no name or address.

## How is it tested?
- `tests/delivery_problems_test.php`: filters and their bound WHERE, days, note, close labels, changes, events, usual days, the screen's pins.
