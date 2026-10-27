---
name: billing-information-widget
description: The Billing Information widget (third classic checkout step): handler and page row, prefill from order and contact, parts the page turns off, helpers shared with step 1.
module: widgets_billing_information
---

## What is it?
- Kind `billing_information` (`includes/fn/widgets_billing_information.php`), the third of the four classic checkout widgets ([[system-widget-missing-types]]). It posts the classic names (`billing_*`, `custom_field_1/2`, `po_number`, `referral_source`, `tax_number`, `tax_office`, `tax_exempt`, `opt_in`, `update_contact`, hidden `page_id`, `next_page_id`, `opt_in_displayed`, `folder_id`) to `billing_information.php`; its checks, contact update and redirects are unchanged.

## Where are the page's settings?
- In the page's `billing_information_pages` row. A designed page gets one at render; `pg_order_page_known($id, 'billing information')` takes a page of the classic type or one with a row, as for express order.
- Widget settings `next_page_id`, `po_number`, `custom_field_1/2_label`, `custom_field_1/2_required` are written only when the widget has the key (`pg_bi_page_row_changes()`).

## What does the form open with?
- After a refused post, the liveform. Else `pg_bi_start_values()`: the order's value, else the signed-in contact's; tax number and office from the contact; opt-in and tax exemption from the order; "update my contact" on unless turned off earlier in the session. Default country; a custom billing form's stored answers (`_pg_sa_prefill_custom_form()`).

## Which parts go?
- `pg_bi_flags()`: a custom field without a label, the PO number when off, the referral source without sources, tax exemption unless tax and exemption are on, the opt-in for a contact already opted in (then `opt_in_displayed=false`), "update my contact" for a visitor not signed in. The block goes by its flag and the control is dropped too.

## What is shared with the shipping address widget?
- The recipient-free parts of `widgets_shipping_address.php`: `_pg_sa_page_id()`, the state text box / pick list switch (`_pg_sa_state_controls()` and `pg_sa_state_script()` with the `billing_` prefix) and the custom form prefill.
