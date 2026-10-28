---
name: workspace-crm-seams
description: Plan (2026-10-23, 1 #1621, 2 #1622, 3 #1666, 4 #1681, 5 #1790, 6 #2092, answers 3-4 given): CRM seams: customer summary, history, follow-ups, lead intake, re-pointing merge, more tag sources
---

## What is asked? (owner, 2026-10-23)
- Seams for a later CRM (in no plan: plans/feature-roadmap-2026-10.md), more for MCP.

## What is there today?
- `contact_id` in `erp_accounts` (erp/erp-account-contact-link.md), `orders`, `forms`, `email_recipients`, `consent_log`; `user.user_contact`.
- `ws_channels.customer_type`, `customer_id`: `ws_customer_present()`, `ws_customer_link_tokens()` (includes/workspace/customer.php); `ws_record_refs()` finds tags, `pg_privacy_export_find()` a person.
- `merge_contacts()` (includes/fn/contacts.php) moves only groups, `opt_in`; other links keep the old id. No pipeline, custom fields, activity; `lead_source` is text.

## Slices
Not approved; a PR each, no schema unless marked; [n] needs slice n. Reads: routes, MCP tools; rows need their record's scope, right, row rule.
1. PR #1621: record-kind registry `ws_record_kinds()` (refs.php; workspace/workspace-record-kind-registry.md): per kind prefixes, right, scope, change kind, audit table, drawer, customer; drift test, lists stay.
2. PR #1622 (workspace/workspace-customer-summary.md): `ws_customer_identity()`, `ws_customer_summary()`, `GET /workspace/channels/{id}/customer`; cart quotes a seam until plans/workspace-quotes.md slice 5; its MCP resource: MCP plan.
3. PR #1666 (workspace/workspace-customer-card.md): a customer card on the channel's Summary tab, the summary through `ws_channel_customer`.
4. PR #1681 (workspace/workspace-customer-history.md): `ws_customer_history()`, union read; route; History on the card. Cart quotes join with quotes slice 5.
5. PR #1790 (workspace/workspace-followups.md): `ws_followup_task_open()`, "Remind me" on the card; next callers: quote expiry (plans/workspace-quotes.md), ERP overdue (plans/workspace-phase3-leftovers.md 4).
6. PR #2092 (workspace/workspace-crm-lead-intake.md): lead step `ws_scheduled_lead()` and box `lead` on `form.submitted`: contact by `forms.contact_id`, else e-mail; reuses a channel from `ws_customer_channels()` or opens one with `builtin:new_customer`; `WS_SCHEDULED_LEAD_HOURLY`; duplicates left to view_contacts.php, `merge_contacts()`.
7. Done (#2421; core/contact-holders.md): merge re-points by `pg_contact_holders()`, tested against erasure; ERP refused if more than one, `consent_log` kept.
8. Contacts in the audit trail: panel, API, Workspace, erasure PR #2170; sync, import, İYS #2246; storefront left.
9. More tag sources, schema, ask first: `ws_refs.source_type` ENUM to VARCHAR (notes, workflow table rows `wtable_row`); `ws_record_refs()` (reads non-messages as tasks) checks `ws_note_access()` or `ws_wtable_*` (new) access per source, or personal notes and tables leak into "where was this record talked about"; next free Workspace label.
10. Later, schema: custom fields, pipelines (question 1) [9]. Candidates: calendar visit (`ws_events.kind` visit), "no order in N days", `lead_source` from `referral_sources`.

## Open questions (recommendation)
1. CRM shape: (a) on the Workspace: pipelines as workflow tables with status, contact columns, (b) own module, (c) seams only now → c, leaning a; the customer stays the contact.
2. Merge (7): (a) re-point orders, forms, email_recipients, ws_channels, ws_refs, cart quotes, ERP account (`erp_account_link_contact()`; refuse if both have one), keep `consent_log` (proof), (b) warn only, (c) a plus `consent_log` → a.
3. History (4): (a) union read, (b) activity table → a.

## Which are answered?
- 3. History (owner, 2026-10-26): (a) union read: no schema, older records count at once.
- 4. Summary route (2): per channel (a), as built.

## Where to start?
- 7, 9, 10 ask first; 8's storefront writers wait on an owner question (core/audit-trail-contacts.md "not hooked").
