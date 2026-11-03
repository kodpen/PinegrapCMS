---
name: api-commission-writes
description: POST /commissions/{id} status (commissions:write) through the screen's write, GET /commissions/recurring-profiles, POST /affiliates/{id}/approve (customers:write), their events
module: commission_list, contacts
---

## Where is a commission's status written?
- `pg_commission_status_save($id, $status, $user_id)` (`includes/fn/commission_list.php`): only a stored status (`pg_commission_posted_status()`), clears `status_before_cancel`, stamps the user and the time, announces `commission.status_changed` only when the status moved. Returns the status before, or `false` (unknown status, no row, failed query). `edit_commission.php` and `api_commissions_update()` (`includes/api/resources/commissions.php`) both call it.
- Route `commissions.update`: `POST /commissions/{id}` (PATCH also accepted), scope `commissions:write`, `dry_run`, body `status` enum required. The handler re-reads and answers the `Commission` object.
- `commissions:write` is the group's write side, given in the commerce ceiling beside `commissions:read`: the screen and its edit open to the same people.

## What do the recurring profile routes answer?
- `GET /commissions/recurring-profiles` and `/{id}` (`commissions.recurring_profiles.*`, `commissions:read`), object `CommissionRecurringProfile`: the `recurring_commission_profiles` row, the order number, the affiliate's name and contact id (the same code lookup as a commission). `commission_limit` is `number_of_commissions`, null for 0 (Unlimited on the screen); `disabled_by_cancel` false where the column is not there yet. Filters `affiliate_code`, `enabled`, `order_id`, `updated_since`; cursor on (`last_modified_timestamp`, id) like `GET /commissions`. Read only: a profile is made by the checkout and switched on its own screen.
- The profile routes are declared before `commissions.get`; `{id}` matches digits only, so `recurring-profiles` never falls into it.

## How is an affiliate approved over the API?
- `pg_affiliate_approve($contact_id, $user_id)` (`includes/fn/contacts.php`): keeps or makes the code (`pg_affiliate_approval_code()`), marks the contact approved, audits, gives the key code for the affiliate group offer (`pg_contact_form_affiliate_key_code()`), sends the welcome e-mail (the code goes through the session, as the sign-up form's welcome reads it) and announces `affiliate.approved` (`pg_affiliate_approved_event()`). `approve_affiliate.php` calls it; the contact form's tick announces the event too but sends no welcome, as before.
- Route `affiliates.approve`: `POST /affiliates/{id}/approve` (contact id), **scope `customers:write`**, not `commissions:write`: `approve_affiliate.php` asks for the contacts right, and the API never opens more than the owner's screens do. The customers row rule applies (`api_customer_reachable_or_fail()`). 409 `affiliate_already_approved`: `pg_affiliate_approve()` itself refuses an approved contact (returns `'already_approved'`, conditional UPDATE so two approvals at once send one welcome), the screen shows a notice for the same answer (PR #5741), 409 `affiliate_program_off`, 422 without an e-mail address. Answers `Affiliate` (id, email, affiliate_name, affiliate_code, approved, commission_rate as per cent). The rate, name and code stay the contact screen's (`POST /customers/{id}` leaves affiliate fields alone).

## Which events?
- `commission.status_changed`: id, reference_code, status, previous_status, affiliate_code, order_id (null for a recurring one without an order), amount. Not announced: the voiding by an order's cancellation and its restore (`commerce/order-cancel-commissions.md`), a new commission.
- `affiliate.approved`: id (contact), affiliate_code, affiliate_name. Not announced: the sign-up form's automatic approval (`customer.created` is).

## Tested?
- `tests/api_commissions_test.php`: routes and scopes, the ceiling, the event payloads, the profile presenter and filters, the affiliate object, the screens calling the shared functions. Not run against a database.
