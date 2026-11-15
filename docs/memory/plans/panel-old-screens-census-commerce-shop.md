---
name: panel-old-screens-census-commerce-shop
description: LM1a census, commerce groups C1–C5: orders, products, customers and offers, marketplaces, shipping and tax forms; screens by root file, what each draws its own way, estimated product lines
---

## C1 Orders (~870 lines)?
- View Order `view_order` (3,416 lines, ~350): 10 old card titles, "Order Details" a `list-group-item` title, 26 badges for status, 22 `small`, 11 inline styles, no `pg-measure-edit`; item tables stay plain (`ui/list-and-table-screens.md`), framed as cards. With it `includes/erp/order_flow.php` (old titles).
- View Return `view_return` (822, ~180): 8 old titles as spans, `h6` titles in the side cards, 19 `small`, no `pg-measure-edit`. Create Return `add_return` (~60).
- Create Order `add_order` + `includes/local_sale.php` (~120): `h5`/`h6` titles, Bootstrap buttons.
- Orders `view_orders` (~100): on the driver, but its Advanced Search form has green `h5 fg-success fw-bold` section titles, a sticky inline style, three summary `card shadow-sm p-5` boxes and an export dialog in old markup.
- One subscription's page in `view_subscriptions` (~60): plain tables and cards.

## C2 Products (~950 lines)?
- Product Builder `product_builder` (Add Product, Edit Product; 8,410 lines, ~600): already the columns with `pg-edit-columns`; 46 inline styles, two `<style>` blocks inside the PHP, 24 Bootstrap button colours, 11 badges, its own section titles (icon, accent colour). If it alone passes 1,000 lines it is its own step and the rest moves to C5.
- Product Group `edit_product_group`, `add_product_group`, `includes/forms/product_group_form.php` (~80); Featured and New Items `edit_featured_and_new_items` (~40, a table); Product Attribute form (~30).
- Barcode Increase / Decrease Inventory `barcode_increase_inventory`, `barcode_decrease_inventory` (near twins, ~90): old titles, 18 old classes each.
- Duplicate Product Group `duplicate_product_group` (~40, no measure), Duplicate Products `duplicate_products` (~20), Import Products `import_products` (~30, old titles).

## C3 Customers, offers, gift cards (~700 lines)?
- Contacts `view_contacts` (~60): on the driver; its Advanced Search has green `h5` titles, the Organize Contacts dialog a card with an inline `max-height`.
- Edit Contact `edit_contact` + `includes/forms/contact_form.php` (~120); Contact Group form (~50); Import Contacts `import_contacts` (~40, old titles).
- Commissions `view_commissions` + `includes/fn/commission_list.php` (~90): cards and `small`; Edit Commission and Recurring Commission Profile are small.
- Approve Affiliate form (~40); Cart Quote `add_cart_quote` (~40); Edit Gift Card `edit_gift_card` (~50, history table).
- Edit Offer `edit_offer` + `includes/templates/edit_offer.php` (~200): 48 old classes, no `pg-measure-edit`, not on the form and status columns.

## C4 Marketplaces (~1,050 lines, two steps)?
- C4a Marketplace Products `marketplace_products` (1,999 lines, ~600): a list not on the driver (`ui/look-screen-widths.md`, #9230), a `<style>` block with 35 literal sizes and colours, 18 Bootstrap buttons, 6 inline styles.
- C4b Marketplace Settings `marketplace_settings` (3,041 lines, ~450): 75 flagged lines, 20 inline styles, a `<style>` block (7 literals), 21 Bootstrap buttons, 37 `small`; not on the settings cards (`pg-set-card`).

## C5 Shipping, tax, currency forms and imports (~450 lines)?
- Titles are on the look already; left are inline styles, Bootstrap buttons, field widths: Shipping Method form (10 inline styles), Zone, Country, State, Tax Zone, Arrival Date (a table), Container (7 inline), Currency, Ship Date Adjustment, Verified Shipping Address, Referral Source forms; the key code template `includes/templates/edit_key_code.php`.
- Old titles: Import Key Codes `import_key_codes`, Import Shipments `import_shipments`, Delete Key Codes `delete_key_codes` (no measure).
