---
name: order-documents-address
description: How the site's order documents (Order Summary widget, its e-mail, the recipients list, the legal documents) print the district and the country, and the starter's payment and arrival date labels
module: widgets_lists_order, legal_documents
---

## Where does the district go on the site's order documents?
- Before the city, left out when it repeats the city (`pg_order_view_district()`), as the panel's order screen, printed order and packing slip print it: "34710 Moda Kadıköy İstanbul".
- One helper builds the line: `_pg_sw_list_order_city_line($zip, $city, $state, $district = '')` (`includes/fn/widgets_lists_order.php`). The Order Summary widget's `__shipping_address_html` / `__shipping_full_address` and each `order.shipments` row's `__shipping_full_address` use it.
- Both `ship_tos` queries of the widget and the legal documents' query read `ship_tos.district` only when `pg_ship_to_district_ready()` says the column exists.
- Before #4568 none of them selected the column: the customer's own district was missing from the summary page, the receipt e-mail and the Pre-information Form / Distance Sales Contract (#4536).

## Which country name do the legal documents print?
- The site's language label, `pg_geo_country_label_site()`, the path the order summary and receipt use: "Türkiye", not the `countries.name` "Turkey" (#4540, #4568).
- Billing and delivery both: the queries keep the stored code; the cart stage takes the posted code from the checkout snapshot and names it the same way. The seller's address is the seller settings' own text and is not touched.

## What does the Order Summary starter label?
- Payment card: `_sdT('Payment method')` ("Ödeme yöntemi"); the shared `Method` key ("Metod") also means a form's POST/GET (#4541).
- Shipping card: the requested arrival date sits in a "Requested arrival date" block with `eo_visible_if: has_arrival_date`, so an "At once" order draws no empty row. It is the date the customer asked for, not an estimate; the timeline's "Estimated delivery" is the estimate (`widgets.php`, shipment steps).
- The starter lives only in `style_designer.src.js`; templates use `'tree' => 'starter'`, so a page built before #4568 keeps its old labels until rebuilt.
