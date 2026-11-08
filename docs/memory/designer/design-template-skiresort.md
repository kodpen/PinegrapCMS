---
name: design-template-skiresort
description: Ski Resort template (pinegrap-skiresort): slopes, lifts and events as collections, lesson requests and stay or group enquiries, passes and rental in the shop, conditions, News, psk- look, tests.
---

## What does it ship?
- Public: `home` (`home_slopes`, `home_events`, `home_posts`), `conditions`, `passes`, `rental`, `ski_school`, `lodging`, `about`, `faq`; `slopes` (`?difficulty=`, `?area=`), `slope`, `slope_new`; `lifts`, `lift_new`; `lesson_request` (`?lesson=`, `?level=`, `?age=`), `lesson_view`, `my_lessons`, `staff_lessons`; `group_enquiry` (`?stay=`, `?group=`), `enquiry_view`, `my_enquiries`, `staff_enquiries`; `events` (today on), `event`, `event_new`; `privacy`, `email_reply` and the request e-mails; shop pages (`buy_passes`, `rental_shop`, `product`, `cart`, `checkout`, `order`, `email_order`) with `requires => ecommerce`; the standard pages with News (`blog`), account and staff pages. The full list is `_test_sk_page_keys()`.
- Catalog: Lift Passes (`SKI-PASS-`), Season Passes (`SKI-SEASON-`), Rental (`SKI-RENT-<key>`, titled "Rental per day: …"), all `shippable => false`; only shop pages and `address_book` need the shop.
- Shared: account_bar, notice, header, footer, staff_bar, snow_band, email_header/footer. Order 135, icon `bi-snow`, look `minimal`, palette `sky`. Contact group `newsletter` (Ski Resort Newsletter). Built in #7958 to #8169 and the assembly.

## Why a main file plus a folder?
- As Museum and Gallery ([[design-template-museum]]): `pinegrap-skiresort.php` includes `parts.php`, `cards.php`, `pages_public`, `slopes`, `mail.php`, `lessons`, `enquiries`, `events`, `shop`, `standard`, `account` in one scope; returns kept as `$…_part` (`$slopes`, `$lifts`, `$lessons`, `$lodging`, `$events`, `$rental` are parts.php's data).
- Its User Styles carry the rules of the five `skiresort-` blocks the pages place ([[designer-blocks-skiresort]]) before the `psk-` rules.

## How does it work without new product behaviour?
- Slopes, Lifts and Events are collections ([[collection-fields]]); a slope's and a lift's status (Open, Closed, On Hold) are pick lists the staff set; difficulty colours are a fixed legend, since a badge cannot follow the record. Events are dated as Restaurant (`$event_on`, `current date` filter).
- A lesson and a stay or group enquiry are requests, never bookings: pick lists by `_cf.url_param`, the first day / arrival with `min_date` today, consent linking `privacy`, office-only status, messages on the record page's Comments widget, `submitter_only` view, the account's and staff's lists.
- Conditions are words the staff edit. Not built: live feeds, a trail map, dated passes, instructor scheduling, lodging availability, webcams.

## What is the psk- look?
- `psk-brand`, `psk-display`, `psk-eyebrow`, `psk-hero` (sky gradient into the page), `psk-stat` (large tabular figures), `psk-quote`, `psk-lift` (cards rise), `psk-price`; Outfit headings, Nunito Sans body, a pair no other template uses (the assembly test checks it). Field ids `skr-` (never `sk-`: secret scanners read it as a key). No fixed dates, no `opacity-*` on text.
- Turkish: "pist", "teleferik", "telesiyej", "teleski", "skipass", "sezon kartı", "ekipman kiralama", "kayak okulu", "ders talebi", "grup talebi", "kar durumu", "Haberler"; the template is "Kayak Merkezi".

## Which tests cover it?
- `design_template_skiresort_test.php` (data, `_test_sk_page_keys()`, `_test_sk_shop_keys()`) and the per-part tests; `…_assembly_test.php`: page order equal to the plan's pages, widgets and shared parts placed, placeholders, `requires`, contact search sections, `psk-` rules, the font pair, four comment threads and the reply card, samples and catalog, the home's widgets.
- In the shared lists of the collection-form (`blog_new`, `event_new`, `lift_new`, `slope_new`, `staff_entry`), account-email, submitter-name, consent-privacy (`skr-lr-consent`, `skr-ge-consent`), faded-text and custom-form-rules (`skr-lr-first-day`, `skr-ge-arrival`) tests; url-params reads every template itself.
