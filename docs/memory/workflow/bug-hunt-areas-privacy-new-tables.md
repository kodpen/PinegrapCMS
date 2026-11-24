---
name: bug-hunt-areas-privacy-new-tables
description: Bug hunt of erasure, export, inventory, merge and keep periods over the 2026.11.0 tables (area 11, #11381): three faults fixed, what was read clean
module: privacy_retention
---

## What was the area?
- Area 11 named by the architect: a person's erasure and export across the tables of 2026.11.0 (volunteers, walk-in queue, prototype verdicts and approver address, contact scores and the visitor journey, share links, launch summary answers). For each: erasure, export, privacy inventory, contact merge, keep-period clear.
- Not touched: the volunteer files of #11338 and the prototype files of #11364 and #11359 (open then); the fixes are in `privacy.php`, `privacy_export.php`, `privacy_retention.php`.

## What did #11381 fix?
- The passive contacts rule took a person who came yesterday: a queue number, a visit tied to the contact and a journey row (campaign click, newsletter, download) were no activity. Now `pg_privacy_rule_activity_checks()` lists them with orders and forms (`core/privacy-retention-rules.md`).
- Erasing a volunteer (or the left-volunteers rule) deleted their coming signed places, but the waitlist never moved up and a swap asked of the place stayed waiting. Now the places are given up first as a Cancel (`core/volunteer-store.md`).
- The export had no section for `lead_browsers`, which the erasure deletes and the inventory counts: the time of the marketing yes was missing. Now `journey_browsers` (`visitors/lead-journey-view.md`).

## What was read and found clean?
- Every new table with personal data is in `pg_privacy_erase_tables()`, `pg_privacy_inventory()` and `pg_contact_holders()` as its store file says (`core/volunteer-store.md`, `commerce/walk-in-queue-store.md`, `designer/prototype-approval-store.md`, `visitors/lead-journey-view.md`).
- Merge: `waiting_tickets`, `volunteers`, the journey tables move; `contact_scores` drop and is worked again; prototype links hold no contact.
- Keep periods: the queue's daily clear empties the same fields as the erasure; the journey trim and the score pass; the left-volunteers rule.
- The queue's export and erasure find numbers by contact, address and number in the same `pg_sms_number()` form.
- `launch_summary_lines` and `ws_share_links` hold no person (staff ids and an agency's question), as decided in their store files.

## Which issues were opened?
- None.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/core/personal-data-erasure.md
