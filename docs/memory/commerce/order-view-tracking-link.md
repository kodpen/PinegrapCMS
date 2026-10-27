---
name: order-view-tracking-link
description: How a parcel's carrier, name and tracking link are found (pg_shipping_carrier: method code, site default, number shape; Turkish capitals), the order view tokens, and how to add a carrier
---

## Which tokens and flags does the order view widget give?
- `__tracking_code` (first parcel's number), `__tracking_codes` (every parcel: number, carrier, linked when known), `__tracking_link` (first parcel's carrier page, empty when unknown), `__tracking_company_name` ("Yurtiçi Kargo"); flags `has_tracking_code`, `has_tracking_link`. Numbers come from `shipping_tracking_numbers`, never from `orders.tracking_code` (a campaign code).
- Each row goes through `pg_sw_order_parcels()` (`includes/fn/widgets_lists_order.php`) → `pg_shipment_view()`: since #2102 the carrier and link stored on the row (11.0.20 columns) win over the guess below, as on the order screen; `order.parcels` rows take the same values. Before, a parcel a marketplace or the panel had named "Aras" showed the carrier guessed from the method code.

## How is the carrier of a parcel found?
- `pg_shipping_carrier($number, $method, $default)` (`includes/fn/ecommerce.php`), in this order: (1) the whole `ship_tos.shipping_method_code` against each carrier's key and aliases, (2) the code a word at a time ("UPS-5GUN", "MNG Ertesi Gün"), (3) the site's default carrier, (4) the number's shape (`patterns`).
- The default comes before the shape (PR #601): the UPS/FedEx/USPS patterns also match the Turkish carriers' plain digits, so an 11-digit Yurtiçi number with a method code that names no carrier ("Standart Teslimat", "TR-STANDART") used to link to UPS.
- Every name is compared through `pg_shipping_carrier_text()`: `İ`→`i`, `I`→`ı`, lower case, then folded to ASCII (`pg_transliterate_to_ascii()`), so "YURTİÇİ KARGO", "YURTICI KARGO" and "yurtıcı" all match. Before #601 `mb_strtolower('İ')` (PHP 7.3+: `i` + combining dot) made "YURTİÇİ KARGO" match nothing.

## Where does the default come from?
- `ECOMMERCE_DEFAULT_TRACKING_PROVIDER` (`data/config.php`; documented in `data/config(default).php`), read by `pg_shipping_default_carrier()`: a key of `pg_shipping_carriers()` in any capitals, '' otherwise.
- `get_shipping_tracking_url()` and `get_shipping_carrier_name()` take an optional `$default`; null (callers: `print_order.php`, the classic `get_view_order_screen_content.php`, `includes/templates/view_order.php`) reads the site's. Before #601 only the widget used it, and only when the method code was empty. The panel order screen reads the row first (`pg_shipment_view()`, `order-screen-shipments.md`).
- System Status's "Default Shipping Carrier" tile reads the value the same way: unknown → warning, known → the carrier's name.

## Which carriers are known?
- `yurtici`, `surat`, `aras`, `mng`, `ptt` (no number patterns: they all issue plain digits), `ups`, `fedex`, `usps`. Each entry: `name`, `aliases`, `url` with `{code}`, `patterns`; order matters (FedEx before USPS).

## How do I add a carrier?
- One entry in `pg_shipping_carriers()`, with a tracking address checked on the carrier's own site; `tests/shipping_test.php` requires `{code}` in it. Add the key to the accepted values in `data/config(default).php`.
- Not yet added for want of a verified address (the carriers' sites refuse cloud sessions' fetches); n11's `HLZ` (Horoz), `CEVA` and `BL` (Borusan) become aliases once their carriers exist. Candidates, all unchecked: HepsiJET `https://www.hepsijet.com/gonderi-takibi/{code}`; Kolay Gelsin `https://www.kolaygelsin.com/tracking?q={code}` (the page exists, the parameter is a guess); CEVA `https://etracking.cevalogistics.com/eTrackResultsMulti.aspx?sv={code}` (CEVA Trak; Turkish parcels unknown); Trendyol Express `https://kargotakip.trendyol.com/?orderNumber=` takes Trendyol's order number, not a tracking number; Sendeo (`sendeo.com.tr/tracking`) and Horoz (an API at developer.horoz.com.tr) showed no link pattern.
