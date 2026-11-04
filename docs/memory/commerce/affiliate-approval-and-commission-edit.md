---
name: affiliate-approval-and-commission-edit
description: approve_affiliate.php checks the contact's groups and does not approve twice; the commission and recurring profile edit screens print a summary of the record
module: commission_list
---

## Who may approve an affiliate?
- `approve_affiliate.php` keeps `validate_contacts_access()` and, after loading the contact, calls `validate_contact_access($user, $contact_id)` like `edit_contact.php`: a role-3 user with access to one contact group cannot open or approve a contact outside their groups; the refusal is logged (PR #5713, #5679).

## What happens on a second approval?
- The request e-mail can be opened again. The GET screen reads `affiliate_approved`: an approved contact gets 'already an approved affiliate' with the code and a Go to Contact button, no form. A POST for an approved contact writes nothing, sends no welcome e-mail and returns to the contact with a notice (PR #5713, #5684); since PR #5741 the refusal lives in `pg_affiliate_approve()` (`includes/fn/contacts.php`, shared with `POST /affiliates/{id}/approve`), which answers `'already_approved'`; the group check stays in the screen above the call. Before, each approval re-sent the welcome e-mail with a BCC to the affiliate address.

## What do the commission edit screens show?
- `pg_commission_edit_summary($record)` (`includes/fn/commission_list.php`, tests in `tests/commission_list_test.php`) prints affiliate name and code, the reference code (commission only), amount, the order linked to `view_order.php` with the 'Cancelled order' badge, and for a profile (a row with `product_name`) product, start date, frequency and number of commissions. `edit_commission.php` and `edit_recurring_commission_profile.php` join `orders` for `order_number` and `orders.status AS order_status` and print it as the Summary card of the status column (PR #5713, #5685). Before, only the reference code was shown and the profile screen showed nothing.

## How are the two edit screens laid out?
- The product_builder pattern (`ui/edit-screen-wide-layout.md`, B11, PR #PRNUM) through `pg_commission_edit_layout($cards, $record, $buttons)`: the Status card (the status select, or the profile's Enable switch) in the form column, the Summary in the sticky status column, the Save button in the sticky save bar. The hidden `id` is the checked integer.
