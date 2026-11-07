---
name: design-template-museum
description: Museum template (pinegrap-museum): exhibitions and highlights as collections, event place and school visit requests, tickets and membership in the shop, timed entry, pmz- look, tests.
---

## What does it ship?
- Home, `visit`, `support`, `about`, `faq`; `exhibitions` (`?status=`) and `collection` (`?department=`) with their record and `_new` pages; `events` (`?kind=`), `event`, `event_new`, place requests (`event_booking`, `booking_view`, `my_bookings`, `staff_bookings`); `learning` and school visit requests (`school_visit` …); `book_online`; the shop pages (`tickets`, `membership`, …); the standard pages with News; account and staff pages; the e-mails. The full list is `_test_mz_page_keys()`.
- Catalog: Admission (tickets) and Membership (tiers) groups, SKUs `MUSEUM-TICKET-<key>` and `MUSEUM-MEMBER-<key>`, `shippable => false`. Prices come from one table in parts.php (`$admission_prices`, `$tier_prices`) through `$money()`; only the shop pages and `address_book` carry `requires => ecommerce`, the visit and support pages say the prices as words.
- Shared: account_bar, notice, header, footer, staff_bar, visit_band, email_header/footer. Order 95, icon `bi-bank2`, look `editorial`, palette `burgundy` (e-mail `#9f1239`). Contact group `newsletter`. Built in #7626 to #7816 and the assembly PR.

## Why a main file plus a folder?
- As School ([[design-template-school]]): `pinegrap-museum.php` includes `parts.php`, `mail.php`, then `pages_public`, `exhibitions`, `objects`, `events`, `learning`, `shop`, `standard`, `account` in one scope, returns kept as `$…_part` (`$exhibitions`, `$objects`, `$events`, `$sessions`, `$people` are parts.php's data, `$catalog` is shop.php's).
- `exhibitions.php` defines the list helpers (`$mz_filter`, `$mz_record_list`, …) the later collections use; `events.php` the request pieces `learning.php` reuses, plus `privacy` and `email_reply`.

## How does it work without new product behaviour?
- Exhibitions are a collection ([[collection-fields]]): status pick list (Now On, Coming Soon, Past), dates as words, gallery, curator, `photo_1`-`photo_3`. Highlights: department pick list, maker, period as words, story, office field `featured`. Events: kind pick list, when/where/price as words.
- A place at an event and a school visit are requests, never a confirmed booking: the event or session by `_cf.url_param`, consent linking `privacy`, office-only status, messages on the record page's Comments widget, `submitter_only` view, the account's and staff's lists. The school visit's preferred date has `min_date` today.
- Timed entry: `book_online` places the booking widget's starter ([[booking-templates]]); the museum sets up entry slots as a bookable service.
- Not built: a dated ticket picked in the cart, slot capacity, member pricing at checkout, Gift Aid, a collection import, an audio guide.

## What is the pmz- look?
- `pmz-brand`, `pmz-display`, `pmz-eyebrow`, `pmz-hero` (a diagonal primary wash), `pmz-lift`, `pmz-price`, `pmz-quote`, `pmz-stat`; Fraunces headings, Work Sans body. Field ids `mz-`. No fixed date in the sample; no `opacity-*` on text.
- Turkish: "sergi", "eser", "koleksiyon", "bilet", "üyelik", "etkinlik", "okul ziyareti", "küratör", "Haberler".

## Which tests cover it?
- `design_template_museum_test.php` (data, shared parts, `_test_mz_page_keys()`) and `…_public_`, `…_exhibitions_`, `…_objects_`, `…_events_`, `…_learning_`, `…_shop_`, `…_standard_`, `…_account_test.php`; `…_assembly_test.php`: catalog, page order, widgets placed once, placeholders, `requires`, search sections, `pmz-` rules, four comment threads and the reply card, samples, Google reviews.
- In the shared lists of the collection-form (`blog_new`, `event_new`, `exhibition_new`, `object_new`, `staff_entry`), account-email, submitter-name, consent-privacy (`mz-eb-consent`, `mz-sv-consent`), faded-text, booking-templates (`school_visit` / `book_online`) and custom-form-rules (`mz-sv-date`) tests; the url-params test covers every template on its own.
