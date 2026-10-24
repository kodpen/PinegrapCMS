---
name: workspace-crm-seams
description: Plan (2026-10-23, step 1 test in #1512, answers open): CRM seams: customer summary, history, follow-ups, lead intake, re-pointing merge, more tag sources
---

## What is asked? (owner, 2026-10-23)
- Seams for a later CRM (in no plan: plans/feature-roadmap-2026-10.md), more for MCP.

## What is there today?
- `contact_id` in `erp_accounts` (erp/erp-account-contact-link.md), `orders`, `forms`, `email_recipients`, `consent_log`; `user.user_contact`.
- `ws_channels.customer_type`, `customer_id`: `ws_customer_present()`, `ws_customer_link_tokens()` (includes/workspace/customer.php); `ws_record_refs()` finds tags, `pg_privacy_export_find()` a person.
- `merge_contacts()` (includes/fn/contacts.php) moves only groups, `opt_in`; other links keep the old id. No pipeline, custom fields, activity; `lead_source` is text.

## Slices
Not approved; a PR each, no schema unless marked; [n] needs slice n. Reads: routes, MCP tools; rows need their record's scope, right.
1. Record-kind registry `ws_record_kinds()` (proposed, refs.php): per kind tag, change kind, audit table, customer flag; drift test on `ws_record_type_keys()`, `ws_change_types()` (plans/workspace-catalog-create.md 1a source reader), `ws_change_audit_target()`, `ws_quick_edit_types()`, `ws_customer_types()`, `pg_audit_tables()` (lists stay). Test #1512; no registry.
2. Summary: `ws_customer_identity()`, `ws_customer_summary()` (new): last orders, `erp_accounts.balance`, open sales quotes (`erp_quote_rows()`, ERP right), cart quotes (ecommerce right) as separate rows, last submissions; `GET /workspace/channels/{id}/customer` (new, `ws_can_read_channel()`); later MCP resource `pinegrap://workspace/channels/{id}/customer` (MCP plan).
3. Panel: channel customer section shows it [2].
4. History: `ws_customer_history()` (new): read-only union: orders, invoices, sales and cart quotes, submissions, `ws_record_refs()` per tied record; route [2].
5. Follow-ups: owns `ws_followup_task_open()` (proposed: tagged task, channel or person, `ws_task_reminder_set()`) for "Remind me about this customer" [3], Sales quote expiry (plans/workspace-quotes.md), ERP overdue (plans/workspace-phase3-leftovers.md item 4).
6. Lead intake: new `ws_scheduled_do()` step (no new kind) on the `form.submitted` event rule opens a `builtin:new_customer` channel for `forms.contact_id` (else by e-mail) or reuses one (`ws_customer_channels()`); hourly cap; duplicates: view_contacts.php, `merge_contacts()`. After #1352, Workspace canvas S1, form protection #1386, #1401 [2].
7. Merge, behaviour change, ask first: `merge_contacts()` re-points (question 2) by one list (proposed) of every `contact_id` holder, shared with erasure (plans/privacy-data-pack-audit-log.md step 4), with `cart_quotes` (proposed), workflow-table cells.
8. Contacts in the audit trail: plans/privacy-data-pack-audit-log.md; writers core/audit-trail-writers.md.
9. More tag sources, schema, ask first: `ws_refs.source_type` ENUM to VARCHAR (notes, workflow table rows `wtable_row`); `ws_record_refs()` (reads non-messages as tasks) checks `ws_note_access()` or `ws_wtable_*` (new) access per source, or personal notes and tables leak into "where was this record talked about"; next free Workspace label.
10. Later, schema: custom fields, pipelines (question 1) [9]. Candidates: calendar visit (`ws_events.kind` visit), "no order in N days", `lead_source` from `referral_sources`.

## Open questions (recommendation)
1. CRM shape: (a) on the Workspace: pipelines as workflow tables with status, contact columns, (b) own module, (c) seams only now → c, leaning a; the customer stays the contact.
2. Merge (7): (a) re-point orders, forms, email_recipients, ws_channels, ws_refs, cart quotes, ERP account (`erp_account_link_contact()`; refuse if both have one), keep `consent_log` (proof), (b) warn only, (c) a plus `consent_log` → a.
3. History (4): (a) union read, (b) activity table → a.
4. Summary route (2): (a) per channel, (b) per record → a.

## Where to start?
- Slice 1, then 2 (read core/personal-data-export.md).
