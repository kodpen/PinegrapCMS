---
name: offer-group-condition
description: Offer condition "The customer is in the contact group" (WS1, #PRNUM): one offer_conditions row, the signed-in customer's contact, a guest never; a quantity break for a group with the products rule
module: ecommerce
---

## What does the condition do?
- An offer with "The customer is in the contact group" applies only to a signed-in customer whose contact (`user.user_contact`) is in that one contact group (`contacts_contact_groups_xref`). Any result can carry it: order or product discount, the ladder, a gift, shipping (#PRNUM).
- A guest, a user with no contact, a contact outside the group, a deleted group: the offer does not apply. The guest view (`pg_offer_guest_view()`: feeds, the price history's offer rows, prices kept for everybody) has no customer, so a group offer never reaches a public price.
- An offer without it is judged as before (J10).

## Where is it kept and judged?
- One `offer_conditions` row: `type` `'contact group'`, `int_value` the group id, `text_value` empty. No schema change: the column is VARCHAR(50).
- Checkout: `pg_offer_customer_conditions_met()` (`includes/fn/ecommerce.php`), which `validate_offer()` and the pending-offer path call before any cart rule; `pg_offer_customer_contact_groups($user_id)` reads the groups once per request, `pg_offer_contact_group_met()` is the pure answer.
- Editor (`edit_offer_f.php`, `offer_editor.js`): offered where `offer_conditions` exists, one group from a list of contact groups by name, "Please select a contact group." for none or one that no longer exists. It is a customer condition: the up-sell never counts it (`_pg_offer_cart_conditions()`). The API's GET answers it as `{type: contact_group, contact_group_id}`; POST /offers does not take it yet.

## How is a quantity break for a group made?
- "Cart contains one of these products" with a quantity (the `offer_rules` row) plus a product discount is a quantity break; the group condition beside it makes it a group's break, without it it is everybody's. No other price engine (J2).

## How is it checked?
- `tests/offer_group_condition_test.php`: a member, a non-member, a user with no contact and a guest; the guest view; an offer without the condition; the break for a group saved as the rule row plus one condition row.
