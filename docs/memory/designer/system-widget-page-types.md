---
name: system-widget-page-types
description: Which classic page types have designer system widgets, which stay `system` nodes (#2436), confirmations as a widget setting, and what a converted page uses until a widget exists.
---

## Which classic page types are system widgets?
- Kinds of `pg_sw_type_labels()` (`includes/fn/widgets.php`) and `SW_TYPES`. Besides the account, form, catalog, cart, calendar, search and error kinds, the classic page types compared on 2026-10-19 (`page_type` cases of `get_page_content.php`) got:
  - multi-step checkout: `shipping_address_and_arrival` (#1746, [[shipping-address-widget]]), `shipping_method` (#1759, [[shipping-method-widget]]), `billing_information` (#1777, [[billing-information-widget]]), `order_preview` (#1789, [[order-preview-widget]]); currency switcher #3164 ([[checkout-currency-picker]]), card fields and surcharge rows #1918, payment services and On Account #3126.
  - `order_form` (#2367, [[order-form-widget]]), `folder_view` (#2465, [[folder-view-widget]]), `photo_gallery` (#2492, [[photo-gallery-widget]]), `affiliate_sign_up_form` (#2508, [[affiliate-sign-up-widget]]).
- Menu, ad and tag cloud regions: [[region-node-to-widget]].

## Which stay `system` nodes, and why?
- #2436 answer 2a (architect, 2026-10-27): e-mail a friend (`get_email_a_friend_screen_content.php`), form view directory (`get_form_view_directory_screen_content.php`) and the affiliate confirmation and welcome pages (`get_affiliate_*`) get no widget. They stay `system` nodes drawn by their classic renderer inside the designer; no kind exists for them.

## How are the confirmation pages covered?
- #2436 answer 1b: no confirmation type. Registration, membership and affiliate sign-up widgets take a next page or a `confirmation_message` setting (#2697); details in [[member-widgets-handlers-and-forms]] and [[affiliate-sign-up-widget]]. A legacy page still goes to the classic confirmation page.

## Why did the multi-step checkout get widgets?
- Owner (2026-10-19): almost every site checks out through it, so it cannot stay a `system` node ("frozen" means its code keeps working). The four widgets post to the classic handlers with their names; the order preview draws `get_order_preview()`'s own values (`workflow/decisions-made-product.md`).

## What does a converted page use until a widget exists?
- A `region` node of type `system` (`<system></system>`) keeps the classic renderer running, so the page works; only its inside is not designable. A custom layout (`layout_type = 'custom'`) keeps drawing through that node too; swapping it for a widget is opt-in per page ([[layout-widget-offer]]).

## How is a new type added?
- `SW_TYPES` + `_swTypeDefaults` ([[system-widget-type-and-name]]), `pg_sw_type_labels()`, a renderer in `includes/fn/widgets*.php` with `_pg_inject_messages_node()` named after the classic liveform, tokens and flags passing `tools/check_bindings.php`. Defaults in their three places and the classic page as yardstick: [[system-widget-change-checklist]], [[system-widget-test-pages]]. One widget per PR.
