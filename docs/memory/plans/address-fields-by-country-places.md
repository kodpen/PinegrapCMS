---
name: address-fields-by-country-places
description: Every place an address's city, state or district is asked, shown or read, checked on 2026-11-01 for plans/address-fields-by-country.md, with what each needs
---

## Which designer system widgets ask for an address?
- Express order (`widgets_express_order.php`): default tree `_eo_default_designer_tree()` (shipping City, District, State / Province; billing City, State / Province), server parts `_eo_render_shipping_section()` and `_eo_render_billing_address()`, box fit `_eo_fit_district_field()`; JS twins `_buildExpressOrderStarterTree()` and the recipient list block ("Add Recipient List Block").
- Shipping address and arrival (`widgets_shipping_address.php`: `pg_sa_district_node()`, `_pg_sa_state_controls()`, `pg_sa_state_script()`), billing information (`widgets_billing_information.php`, the same script with `billing_`), affiliate sign-up (`widgets_affiliate_sign_up.php`, the same script).
- Account profile (business city/state) and address book (`widgets_account.php`): the state is a text box with no pick list (A3).
- JS starters of all five: `_buildStarterTree()` cases in `style_designer.src.js` (A2: default country's words).
- Showing only: order view and its lists, shipping method, order preview, cart (`pg_order_view_district()` already drops a district equal to the city). Registration and my account ask for no address.

## Which design templates hold address forms?
- Store, boutique, bookshop, hello and events (`pinegrap-events/tickets.php`, #4737) place express order; every finished template places account profile and address book (salon in `pinegrap-salon/account.php`). All open them with `'tree' => 'starter'`; no template ships its own address tree. A2's starters cover new openings, A1's render covers installed designs.

## Which classic pages and layouts?
- `get_express_order.php`, `get_shipping_address_and_arrival.php` (with the district box), `get_billing_information.php`, `get_update_address_book.php`, `get_my_account_profile.php`, `get_affiliate_sign_up_form_screen_content.php`; all switch the state through `software.init_country()` (`frontend.src.js`).
- Generated layouts: `includes/templates/express_order.php`, `shipping_address_and_arrival.php`, `billing_information.php`, `update_address_book.php`, `my_account_profile.php`.
- Turkish default install layouts already say İlçe/İl: 144 (shipping), 179 and 1076 (billing), 256 and 476 (express order), 975 (profile, "İlçe/Semt"), 976 (address book). 1005 (new-conversation form) says "City" / "State / Province" (A4). The English default's say City / State / Province: default words, they swap.

## Which panel and ERP screens?
- `includes/forms/verified_shipping_address_form.php` ("City"); ERP `includes/erp/account_form.php` (district, city, state; Turkey hides the state) and `includes/erp/waybill_form.php`.
- Already İlçe/İl in Turkish, no change: the contact form ("İşletme İlçesi/Şehri", "İşletme Eyaleti/İli") and Settings › General's organization address ("Organizasyon İlçe/Şehri", "Kuruluş Eyaleti/İl").
- Shown only, unchanged: `view_order.php`, `print_order.php`, `print_packing_slip.php`, order lists, API order fields.

## Which paths save or read it?
- Save: `express_order.php` and `shipping_address_and_arrival.php`, both through the `pg_ship_to_district_*` helpers; billing, profile, address book and affiliate keep city and state as posted.
- Read: `erp_address_from_checkout()` (account card, Paraşüt, e-waybill), the carriers (`pg_label_recipient_district()`: district, else city), the n11 and Trendyol imports (already city = district, state = province, district filled).
