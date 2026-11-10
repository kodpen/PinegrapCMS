---
name: audiences-door-join
description: pg_audience_door_join(): storefront sign-up doors put a contact into the registration, membership or a ticked group and announce the join for flows, campaign profiles and webhooks
module: audience_doors
---

## Which doors use it?
- The registration or membership group of a new account: sign-up in `includes/fn/auth.php` (twice), `includes/fn/signin.php`, contact creation in `includes/fn/contacts.php`, `my_account_profile.php`, `email_preferences.php`; and a group ticked on `email_preferences.php`.
- Orders and checkout: the groups a product sale or the reward program collects (`pg_order_complete_contact_groups()`, one call per group) and the registration group of an order's auto-registration (`pg_order_auto_register()`), both in `includes/fn/order_completion.php`; the Orders group at checkout (`submit_order.php`, `billing_information.php`, `express_order.php`), which still finds or creates the Orders group itself.
- The affiliate group (`AFFILIATE_CONTACT_GROUP_ID`) on `affiliate_sign_up_form.php`.
- None of these writes the `source` column; the rows keep its default `hand`. `pg_announce()` queues the webhook and never sends it in the request, so a payment does not wait on a receiver.

## What does it do?
- `pg_audience_door_join($contact_id, $group_id)` (`includes/fn/audience_doors.php`): skips a group filled by rules or missing (`pg_audience_hand_sql()`) and a contact already in it, inserts the xref row, then announces through `pg_announce_contact_group_members()` with the group's name.
- `pg_audience_door_joined()` (pure) decides the announced list: the contact only when a new row was written.

## Why?
- These doors inserted the row directly, so "joined a group" campaign profiles, `contact_group.joined` flows and the `contact_group.members_changed` webhook never started for a sign-up, an order, a checkout or an affiliate sign-up, unlike every other door (owner decision #6599/#6625: the one seam).

## Tests?
- `tests/audiences_door_join_test.php`: the pure decision and a scan that each door calls the helper and that the order, checkout and affiliate files keep no insert of their own; `tests/audiences_doors_test.php` names it as their guard.
