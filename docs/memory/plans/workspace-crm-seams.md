---
name: workspace-crm-seams
description: Plan (2026-10-23, not started, answers open): seams for a later CRM: one customer identity, its summary and history, a merge that re-points, tags on more sources
---

## What is asked? (owner, 2026-10-23)
- Ready the Workspace for a later CRM, give an AI on MCP more to do, find more. CRM is in no plan (plans/feature-roadmap-2026-10.md).

## What is there today?
- Three records tied through the contact: `contacts`, `user.user_contact`, `erp_accounts.contact_id` (erp/erp-account-contact-link.md); `orders`, `forms`, `email_recipients`, `consent_log` carry `contact_id`.
- A channel's customer (`ws_channels.customer_type`, `customer_id`): `ws_customer_present()` (includes/workspace/customer.php) draws it and its ties (`ws_customer_link_tokens()`); `ws_record_refs()` finds a record's tags; `pg_privacy_export_find()` a person.
- `merge_contacts()` (includes/fn/contacts.php) moves only groups and `opt_in`; every other link keeps the deleted id.
- No pipeline, custom fields or activity; contact groups are lists; `lead_source` is text; notes get no `ws_refs` row.

## What is proposed? (not approved yet)
- The customer stays the contact (workspace/workspace-module-decisions.md); a CRM builds on channels, tasks and proposed workflow tables (plans/workspace-canvas-workflow-tables.md): a pipeline = a table with status and contact columns.
- Each read is a route, so an MCP tool; a row needs its record's scope and right, as tags do.
- Candidates: a customer on a `ws_events` `visit` (schema); metric "no order in N days"; a guest accepting a quote by approval link (`erp_quote_set_status()`); same-e-mail duplicate hint; `lead_source` from `referral_sources`; saved replies (schema).

## Slices
One PR each; [n] needs slice n.
1. Registry, no schema: `ws_record_kinds()` (new, refs.php): per kind its tag, change kind, audit table, customer flag; a pure test fails when `ws_record_type_keys()`, `ws_change_types()` (needs the database: read from source), `ws_change_audit_target()`, `ws_quick_edit_types()`, `ws_customer_types()` or `pg_audit_tables()` drift. Lists stay.
2. Summary, no schema: `ws_customer_identity()`, `ws_customer_summary()` (new): last orders, `erp_accounts.balance`, open quotes (`erp_quote_rows()`), last submissions; `GET /workspace/channels/{id}/customer` (new) behind `ws_can_read_channel()`.
3. Panel, no schema: the channel's customer section shows it [2].
4. History, no schema: `ws_customer_history()` (new): read-only union of orders, invoices, quotes, submissions, `ws_record_refs()` per tied record; route [2].
5. Follow-ups, no schema: "Remind me about this customer" (a tagged task, `ws_task_reminder_set()`) [3]; metric `erp_quote_expiring_count()`. ERP overdue: plans/workspace-phase3-leftovers.md item 4.
6. Lead intake, no schema: new scheduled action kind: `form.submitted` opens a `builtin:new_customer` channel for `forms.contact_id` (else by e-mail) or reuses one (`ws_customer_channels()`) [2].
7. Merge, no schema, behaviour change, ask first: `merge_contacts()` re-points `orders`, `forms`, `email_recipients`, `ws_channels`, `ws_refs`, ERP via `erp_account_link_contact()`; two accounts refuse; `consent_log` (proof): owner.
8. Audit trail, no schema, ask first: contacts into `pg_audit_tables()`, writers (core/audit-trail-writers.md); widens plans/privacy-data-pack-audit-log.md.
9. Tags on more sources, schema, ask first: `ws_refs.source_type` ENUM to VARCHAR (notes, table rows); `ws_record_refs()` reads non-messages as tasks; next free Workspace label.
10. Later, schema: custom fields, pipelines on workflow tables [9].

## Open questions (recommendation)
1. CRM shape: (a) on the Workspace, (b) own module, (c) seams only now → c, leaning a.
2. Merge (7): (a) re-point, (b) warn only → a.
3. History (4): (a) union read, (b) activity table → a.
4. Summary route (2): (a) per channel, (b) per record → a.

## Where to start?
- Slice 1, then 2; read core/personal-data-export.md first.
