---
name: workspace-flows-people-content-start-events
description: People and content webhook events a flow can start on (flows-next C2): user account, consent, bounce, campaign, page, calendar reservation, store credit; record, right, kept payload, outputs
---

## Which events, and where are they listed?
- `user.created`, `customer.consent_changed`, `mail.bounced`, `campaign.sent`, `page.created`, `page.updated`, `calendar_event.reserved`, `credit.issued`, `credit.expired`: in `pg_event_workspace_events()`, `ws_events_catalog()` and `ws_flow_event_outputs()`.
- `calendar_event.reserved` and store credit sit in the shop block; credit only once `pg_credit_ready()`. The rest are always listed.
- None is written into watching channels (`ws_events_watched()` unchanged).

## What record and right does each have?
- `user.created`: the `user_account` (payload `id`), right `users` (roles 0-2, as the user screen). Announced only by the API's user create.
- `customer.consent_changed`: the contact (`id`), right `contacts`.
- `mail.bounced`: the contact of the address (`contact_id`, the oldest contact with that e-mail), none when no contact has it; right `contacts`.
- `campaign.sent`: no tagged record (record ''), right `emails`: `ws_viewer()` now carries `emails` (roles 0-2 or `manage_emails`, as `validate_email_access()`); `ws_change_viewer_from_row()` passes `user_manage_emails`.
- `page.created` / `.updated`: the `page`, right `member`, and checked against the creator (`ws_events_checks_creator()`): their tag of the page must resolve (`ws_refs_resolve()` known: the folders they reach).
- `calendar_event.reserved`: the `calendar_event` (`id`), right `calendars`.
- `credit.issued` / `.expired`: the customer's contact (`customer_id`), none for an account without one; right `ecommerce`.

## What does the Workspace keep that the webhook does not?
- `pg_event_workspace_payload()` in `pg_event_record()`: a consent's nested `consent` laid flat as `consent_channel`, `_purpose`, `_status`, `_source`; a bounce's `suppressed` as 0/1 (a yes/no output reads as '') and its `contact_id`. The webhook's body stays as announced.

## Does the API's page update reach the Workspace?
- Yes, through `api_webhook_enqueue()`: it calls `pg_event_record()` itself unless `$recorded` says `pg_announce()` already did. A second `pg_event_record()` beside it would keep the event twice and start each waiting flow twice.

## What do the start box's outputs give?
- Single values only: a user's username, email, role; the consent fields; a bounce's address, kind, source, suppressed, contact_id, campaign_id; a campaign's id, channel, type; a page's name; a reservation's repeat, quantity, order id and number, contact_id; credit's customer_id, account_id, kind, reason, expires_at. Amounts in minor units are not offered.
- Tests: `tests/workspace_people_content_start_events_test.php`; parity lines in `tests/workspace_flows_parity_test.php`.
