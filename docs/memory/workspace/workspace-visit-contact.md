---
name: workspace-visit-contact
description: A customer visit on the Work Calendar names its contact (ws_events.contact_id, 11.0.118, CRM seams 10.1, #3517): the pick check, the card section, the history entry, holder and erasure
---

## What is it?
- CRM seams step 10.1, decided in #3416 1(a) (`workflow/decisions-made-schema-5.md`): `ws_events.contact_id INT UNSIGNED NOT NULL DEFAULT 0`, `idx_contact`, step `upgrade_2026_11_0_workspace_visit_contact()` (11.0.118). Older items keep 0. Readers ask `ws_event_contacts_ready()` (`pg_schema_has()`); before the upgrade nothing is read or written.
- Code: includes/workspace/schedule.php (`ws_event_save_check()`, `ws_event_save()`, `ws_event_contact_pick()`, `ws_event_contact_of()`, `ws_event_contact_label()`); tests: tests/workspace_visit_contact_test.php.

## Which items name a customer, and who may pick one?
- Only kind `visit`; saving any other kind writes 0, so a visit turned into a meeting drops its customer.
- `contact_id` left out of the request keeps what the item has: the API (`ws_api_plan_item_input()` does not send it), /meeting, /away and older forms never drop it. Sent back unchanged it needs no check, so a teammate who cannot open the contact may still edit the visit.
- A new contact must exist and be among `ws_viewer_contacts_open()` of the person saving (staff every contact; role 3 only with the contacts right and in their contact groups); otherwise "That customer could not be found." on field `contact_id`, the same refusal as for a contact that is not there.

## How does the form show it?
- `ws_event_get` sends `contact` (id, label, hidden) instead of `contact_id`; a contact the reader cannot open comes as hidden with no id or name, shown as "A customer you cannot open"; it can be removed, not read.
- `eventForm()` in assets/js/workspace.js draws "Customer (optional)" for kind visit while `CFG.event_contacts` is on: a search through `ws_ref_search` type `contact` (as the Flows run picker), and sends `contact_id` only once it was changed there.

## Where does it show?
- Customer card: section "Customer visits", the latest 5 (`ws_customer_history_visit_rows()` with before 0, built by `ws_customer_card_visits()`), each opening `workspace_calendar.php?month=&event=`. Added in `ws_customer_card()` only: the API's summary (`ws_customer_summary()`) keeps its sections.
- History: source `visits` under the Workspace right (every member reads the plan), entry kind `visit`, `at` = `starts_at`, so a visit still to come stands first. In the API route's description too.

## Merge and erasure?
- `pg_contact_holders()` row `ws_events` (merge `move`, erase `find`): a merge re-points visits; the erasure finds them by the person's contacts and sets `contact_id` 0 (`pg_privacy_erase_tables()`, label "Customer visits"). The visit stays on the team's plan; its title and note are not touched.

## What is next?
- External API and MCP field `contact_id` on plan items (`includes/workspace/api.php` route params, the PlanItem object, `ws_api_plan_item_input()`, `ws_api_plan_item_would()`); not in #3517.
- Not checked live: no running site; the step, the form and the card never ran against a database or in a browser.

Related: docs/memory/plans/workspace-crm-seams.md, docs/memory/workspace/workspace-customer-card.md, docs/memory/workspace/workspace-customer-history.md, docs/memory/core/contact-holders.md
