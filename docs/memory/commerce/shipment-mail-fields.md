---
name: shipment-mail-fields
description: Tracking mail-merge fields of the order_shipped and order_delivered e-mails, which addresses they cover, and step 11.0.42 widening email_campaigns.action
---

## Which fields do the order e-mails get? (PR #1964)
- Beside `^^order_number^^`: `^^tracking_numbers^^` (every numbered parcel, ", "), `^^carrier_name^^` (each carrier's name once, first met first) and `^^tracking_url^^` (the first parcel that has a link; one link is what an `href` holds).
- Built by `pg_shipment_merge_fields()` (pure, `tests/shipping_test.php`) from `pg_shipment_view()` of each row of `pg_shipment_rows()`; `pg_shipment_mail_fields($ship_to_ids)` reads the rows, the address's `shipping_method_code` and the site's default carrier, so the e-mail shows the carrier and link the order screen shows.
- All three are always passed, empty when nothing is known, so `[[...^^tracking_url^^...||...]]` takes its else part. `replace_variables()` escapes them for HTML.

## How is ^^tracking_url^^ written as a link?
- `<a href="^^tracking_url^^">`, or a button made with the editor's Link dialog, which stores `http://^^tracking_url^^`. `pg_mail_merge_address_hrefs()` (`includes/fn/mail.php`, default fields `review_url` and `tracking_url`) takes that `http://` off before the fields are filled; without it the e-mail linked to `http://https://…`. An `href` starting with `^^` gets no site address or tracking code (`tests/mail_merge_hrefs_test.php`).

## Which addresses do they cover?
- order_shipped (`update_order()`): the addresses this save marked shipped (`$recipient['shipped']`), not every address of the order.
- order_delivered (`pg_shipment_delivered()`): that one address, read after its rows were stamped delivered.
- The SMS texts build their own fields (`pg_sms_order_shipped()`, `sms_messages.php`).

## Why the step 11.0.42?
- A campaign copies its profile's action into `email_campaigns.action`. 11.0.20 widened only `email_campaign_profiles.action` with `order_delivered`, so a delivered campaign was stored with action '' (seen on a sandbox). `upgrade_2026_11_0_delivered_campaigns()` reads the enum and only widens it, keeping the default '' and the null rule (the same way `upgrade_2026_11_0_conversion_pack()` added `order_review_request`). A fresh install runs every step from the starter site, so it gets the value the same way.
- Owner approval: 2026-10-20, recorded in `plans/shipping-management.md`.

## Where is order_delivered offered?
- `add_` / `edit_email_campaign_profile.php` (action list, commerce-only gate, schedule "after action", `action_item_id` 0) and `view_email_campaign_profiles.php` ("Order Delivered").
