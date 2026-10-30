---
name: tax-zone-commission-reward-boxes
description: Rate, commission and reward point boxes read as their columns store them: tax zone rate, contact commission, user points, Site Settings surcharge, default commission and reward goal
---

## How is a tax zone's rate read on save?
- `add_tax_zone.php` and `edit_tax_zone.php` read `$_POST['tax_rate']` through `parse_tax_rate()`: a comma is the decimal separator (`8,25` is 8.250) and the value is held to 0..100. Before, the raw text was written and the non-strict session stored `8,25` as 8.000, which undercharged tax at checkout.
- `tax_zones.tax_rate` is `NOT NULL DEFAULT 0.000`, so `parse_tax_rate()`'s NULL (blank or unreadable) is refused with "Enter a tax rate between 0 and 100.", never written as 0. The edit screen refuses before it drops the zone's country and state links.
- The box (`pg_tax_zone_form_cards()`) is the product screen's: `type="number" min="0" max="100" step="0.001"`, plus `required`; it is filled through `format_tax_rate()` (8.250 reads 8.25, 0.000 reads 0).

## How wide is the contact commission rate box?
- `contacts.affiliate_commission_rate` is `decimal(5,2) unsigned`; the box in `includes/forms/contact_form.php` takes `maxlength="6"` with `inputmode="decimal"`. The old `maxlength="3"` could not take 12.5 or show a stored 12.50 (the product screen had the same bug, `product-screen-units-and-commission.md`).

## How are reward points bounded?
- `user.user_reward_points` is `int(10) unsigned`. The Reward Points box on `add_user.php`, `edit_user.php` and `import_users.php` is `type="number" min="0" max="4294967295" step="1"`; browsers ignore `maxlength` on a number box, and -5 was stored as 0.

## How does Site Settings read its rate and goal boxes?
- `includes/settings/commerce.save.php` reads Surcharge (%) through `pg_settings_surcharge_percentage()` (`parse_tax_rate()`, 0..100, blank or unreadable is `0`, which prep.php shows as an empty box), Default Commission Rate (%) through `pg_parse_commission_rate()` (last `.` or `,` is the decimal, 2 places, 0..999.99, no 100% cap, as on the product screen) and the Reward Program goal through `pg_parse_reward_goal()` (`pg_parse_amount()`, rounded, 0..4294967295).
- Before, the raw text was written: the dialog's `data-inputmask-alias="decimal"` is never bound (the pane arrives as JSON, and `pgInitInjected()` masks only `.input-mask-key-code`), so the non-strict session stored `2,5` as 2.000, `7,5` as 7.00 and a Turkish goal of `1.000` as 1.
- The two percent boxes open `inputmode="decimal"`; the goal box stays `numeric`.
- The Merchant Center shipping rate (`seo.save.php`) is read through `pg_parse_amount()`; blank stays -1. Before, `1.250,00` became 1.25.

Tests: `tests/rate_boxes_store_typed_test.php`, `tests/settings_commerce_rates_test.php`.
