---
name: workspace-crm-seams
description: Plan (2026-10-23; 1-9 done, PRs #1621 to #3135; answers 2-4 given): CRM seams: customer summary, history, follow-ups, lead intake, re-pointing merge, more tag sources
---

## What is asked? (owner, 2026-10-23)
- Seams for a later CRM (in no plan: plans/feature-roadmap-2026-10.md), more for MCP.

## What is there today?
- `contact_id` in `erp_accounts` (erp/erp-account-contact-link.md), `orders`, `forms`, `email_recipients`, `consent_log`; `user.user_contact`.
- `ws_channels.customer_type`, `customer_id`: `ws_customer_present()`, `ws_customer_link_tokens()` (includes/workspace/customer.php); `ws_record_refs()` finds tags, `pg_privacy_export_find()` a person.
- `merge_contacts()` (includes/fn/contacts.php) moves only groups, `opt_in`; other links keep the old id. No pipeline, custom fields, activity; `lead_source` is text.

## Slices
A PR each, no schema unless marked; [n] needs slice n. Reads: routes, MCP tools; rows need their record's scope, right, row rule.
1. PR #1621: record-kind registry `ws_record_kinds()` (refs.php; workspace/workspace-record-kind-registry.md): per kind prefixes, right, scope, change kind, audit table, drawer, customer; drift test, lists stay.
2. PR #1622 (workspace/workspace-customer-summary.md): `ws_customer_identity()`, `ws_customer_summary()`, `GET /workspace/channels/{id}/customer`; cart quotes a seam until plans/workspace-quotes.md slice 5; its MCP resource: MCP plan.
3. PR #1666 (workspace/workspace-customer-card.md): a customer card on the channel's Summary tab, the summary through `ws_channel_customer`.
4. PR #1681 (workspace/workspace-customer-history.md): `ws_customer_history()`, union read; route; History on the card. Cart quotes join with quotes slice 5.
5. PR #1790 (workspace/workspace-followups.md): `ws_followup_task_open()`, "Remind me" on the card; next callers: quote expiry (plans/workspace-quotes.md), ERP overdue (erp/erp-overdue-reminders.md).
6. PR #2092 (workspace/workspace-crm-lead-intake.md): lead step `ws_scheduled_lead()` and box `lead` on `form.submitted`: contact by `forms.contact_id`, else e-mail; reuses a channel from `ws_customer_channels()` or opens one with `builtin:new_customer`; `WS_SCHEDULED_LEAD_HOURLY`; duplicates left to view_contacts.php, `merge_contacts()`.
7. PR #2591 (#2421; core/contact-holders.md): merge re-points by `pg_contact_holders()`, tested against erasure; ERP refused if more than one, `consent_log` kept.
8. Contacts in the audit trail: panel, API, Workspace, erasure PR #2170; sync, import, İYS #2246; storefront, merge #2620; membership and recurring jobs, checkout tax fill #2643; designed forms (`custom_form.php`) #2768. Done.
9. Done PR #3135 (decided #3138, `workflow/decisions-made-schema-4.md`; workspace/workspace-ref-sources.md): `ws_refs.source_type` to VARCHAR(20) (11.0.116), sources `note` and `wtable_row` with `channel_id` 0 behind `ws_ref_sources_ready()`; drawer, badge, `GET /workspace/refs` and the history check `ws_note_access()` / `ws_wtable_access()` per source; older notes and rows by the general job's budgeted backfill, cursor in `config_text`.
10. Decided #3416 (`workflow/decisions-made-schema-5.md`, `-workspace-4.md`): 3 done #3427 (core/contact-lead-source.md; API param next); 1 done #3517 (workspace/workspace-visit-contact.md; API field next); 2 no-order start next (10.2); custom fields, pipelines: question 1 [9].

## Open questions (recommendation)
1. CRM shape: (a) on the Workspace: pipelines as workflow tables with status, contact columns, (b) own module, (c) seams only now → c, leaning a; the customer stays the contact.
2. Merge (7): answered (#2421), (a), as slice 7 says.
3. History (4): (a) union read, (b) activity table → a.

## Which are answered?
- 3. History (owner, 2026-10-26): (a) union read: no schema, older records count at once.
- 4. Summary route (2): per channel (a), as built.

## Where to start?
- 10.2 (the no-order Flows start) after the queued Flows work; the API fields of 3 and 1 (`includes/api/`).
