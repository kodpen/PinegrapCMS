---
name: workspace-personal-plan-customer
description: A person's own plan made for a customer (PC6): contact_id, the drawer field, Roadmaps on the card and history, New roadmap, merge and erasure, not in the API
---

## Where does it live?
- `includes/workspace/personal_plan_contact.php` (loaded by `bootstrap.php`), on `ws_personal_plans.contact_id` of step 11.0.201; no column was added. One of the files the plan-table guard allows (`tests/workspace_personal_plans_test.php`).
- A plan names a contact only by its owner (`ws_personal_plan_contact_save()`, `ws_personal_plan_can_edit()`), and a new one only when the owner may open it (`ws_viewer_contacts_open()`, checked by `ws_event_contact_pick()` as a visit's). 0 clears it.

## How does the drawer show it?
- "Customer (optional)" under the note: the visit's picker (`eventCustomerPicker()`, handed to My calendar as `h.customerPicker`), with "The plan shows on the customer's card and history, only to those who may see the plan." A contact the reader may not open is shown as hidden; a shared reader sees it read-only.
- Sent only once changed there, after the plan is saved (`ws_personal_plan_contact_save`), before the roadmap's steps.

## Where does it show?
- Customer card (`ws_customer_card()`): section "Roadmaps", the latest 5 plans naming the contact that the reader may see (`ws_personal_plans_of_contact()`, every row through `ws_personal_plan_can_see()`), with "Plan of <owner>" for somebody else's, the day and "2/5"; each opens My calendar on the plan.
- History (`ws_customer_history()`, Workspace right): entry kind `plan` at the plan's start, detail "Roadmap" or "Plan of <owner>", status "2/5"; an all-day plan says its day alone (`day_only`), and on a narrow screen its title gives way, not its kind. Not in the API's history: `ws_api_channel_customer_history()` sets `history_plans` false, so no application reads a plan.
- A customer never sees a roadmap on the site (owner, 2026-11-14).

## What does "New roadmap" on the card do?
- Offered (`ws_customer_card_roadmap_offer()`) to a team member who may open the customer's contact. "Empty roadmap" opens My calendar with `?new_plan=1&contact=<id>`: the New plan drawer with the customer filled in. "Copy one of my plans" lists the person's 5 latest own plans.
- "Team templates" follow: the team's roadmap templates, each filling the New plan drawer (`workspace-roadmap-templates.md`).
- A copy writes nothing (architect, channel 3345): the plan's link opens My calendar with `&from=<plan>`, and the New plan drawer is filled by `ws_personal_plan_copy_draft()` (`ws_personal_plan_copy_shift()` pure): title, note, the customer, the steps; it starts today, keeps its length, time of day and each step's distance to the start; nothing done, no task tied, no reminder, Only me. The plan exists only after Save.

## Merge, erasure and export?
- `pg_contact_holders()` row `ws_personal_plans` (merge `move`, erase `find`); the erasure sets `contact_id` 0 (`pg_privacy_erase_tables()`, label "Roadmaps"); the plan stays its owner's.
- Export: a customer's export lists the plans naming them that are shared with the whole team, never others (PC6b, owner 3361, `workspace-personal-plan-export.md`).

- The card's actions (New cart quote, Remind me, New roadmap) stand as one group under the card's title, wrapping from the left (architect, 3345).
- Erasure does not touch the mail (owner, channel 3364).

Tests: `tests/workspace_personal_plan_contact_test.php`, `tests/contact_holders_test.php`, `tests/privacy_erase_test.php`.
