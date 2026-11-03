---
name: address-form-places
description: Every place an address's city, state or district is asked, shown, saved or read: widgets, templates, classic pages, layouts, panel and ERP forms, save and read paths
module: shipping
---

## Which designer system widgets ask for an address?
- Express order (`widgets_express_order.php`): default tree `_eo_default_designer_tree()`, server parts `_eo_render_shipping_section()` and `_eo_render_billing_address()`, box fit `_eo_fit_district_field()`, state pick list `_eo_fit_state_lists()` (`checkout-state-list.md`); JS twins `_buildExpressOrderStarterTree()` and the recipient list block.
- Shipping address and arrival (`widgets_shipping_address.php`), billing information (`widgets_billing_information.php`), affiliate sign-up (`widgets_affiliate_sign_up.php`), account profile and address book (`widgets_account.php`).
- Showing only: order view and its lists, shipping method, order preview, cart (`pg_order_view_district()` drops a district equal to the city). Registration and my account ask for no address.
- A change to one of these follows `designer/system-widget-change-checklist.md`; labels `address-labels-by-country.md`, editor `designer/address-labels-in-editor.md`.

## Which design templates hold address forms?
- Store, boutique, bookshop, hello and events (`pinegrap-events/tickets.php`) place express order; every finished template places account profile and address book (salon in `pinegrap-salon/account.php`). All open them with `'tree' => 'starter'`; no template ships its own address tree, so the starters cover new openings and the render covers installed designs.

## Which classic pages and layouts?
- `get_express_order.php`, `get_shipping_address_and_arrival.php`, `get_billing_information.php`, `get_update_address_book.php`, `get_my_account_profile.php`, `get_affiliate_sign_up_form_screen_content.php`: the state through `software.init_country()` (`frontend.src.js`).
- Generated layouts: `includes/templates/express_order.php`, `shipping_address_and_arrival.php`, `billing_information.php`, `update_address_book.php`, `my_account_profile.php`.
- Turkish default install layouts say İlçe/İl: 144 (shipping), 179 and 1076 (billing), 256 and 476 (express order), 975 (profile, "İlçe/Semt"), 976 (address book), 1005 (new conversation).

## Which panel and ERP screens?
- `includes/forms/verified_shipping_address_form.php`; ERP `includes/erp/account_form.php` (district, city = il, state hidden for Turkey) and `includes/erp/waybill_form.php`; district suggestions `place-packs.md`.
- Already İlçe/İl in Turkish: the contact form and Settings › General's organization address.
- Shown only: `view_order.php` (its billing card labels by `pg_address_labels()`, `checkout-state-list.md`), `print_order.php`, `print_packing_slip.php`, order lists, API order fields.

## Which paths save or read it?
- Save: `express_order.php` and `shipping_address_and_arrival.php` through `pg_ship_to_district_*` (`tr-district-plate-code.md`); a listed spelling in those and `billing_information.php` (`tr-place-list.md`); profile, address book and affiliate keep what is posted.
- Read: `erp_address_from_checkout()` (account card, Paraşüt, e-waybill), the carriers (`pg_label_recipient_district()`: district, else city), the n11 and Trendyol imports (city = district, state = province, district filled).
