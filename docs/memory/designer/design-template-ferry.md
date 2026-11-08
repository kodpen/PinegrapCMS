---
name: design-template-ferry
description: Ferry template (pinegrap-ferry): routes with a page each, ports, timetables and fares as words, Travel Notices collection, booking requests, group enquiries, no shop, pfy- look, tests.
---

## What does it ship?
- Home (places `home_notices`, `google_reviews`, `home_posts`), `routes` and one `route_<key>` per route (bay_gull, bay_cedar, north_cedar, gull_cedar), `ports`, `timetables`, `fares`, `onboard`, `travel`, `about`, `faq`; `notices` (`?route=`), `notice`, `notice_new` (staff); `booking_request` (`?route=`, `?fare=`), `booking_view`, `my_bookings`, `staff_bookings`, `privacy`, `email_reply`; `group_enquiry` (`?route=`), `group_view`, `my_groups`, `staff_groups`; the standard pages with the blog called News ([[design-templates-standard-pages]]); sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`; the e-mail pages.
- No shop and no catalog: the booking office confirms and takes payment (only `address_book` carries `requires => ecommerce`).
- Shared: account_bar, notice, header, footer, staff_bar, booking_band, email_header/footer. Order 117 (113 is sports club's), icon `bi-water`, look `minimal`, palette `ocean`. Contact group `newsletter`. Built in #7873–#8053 and the assembly.

## Why a main file plus a folder?
- As Marina ([[design-template-marina]]): `pinegrap-ferry.php` includes `parts.php`, `mail.php`, then `pages_public`, `notices`, `bookings`, `standard`, `account` in one scope, returns kept as `$…_part`. `parts.php` owns `$routes`, `$ports`, `$seasons`, `$fares`, `$figures`, `$page`; the main file never uses those names.
- `notices.php` defines the filter and link pieces (`$fy_filter`, `$fy_page_link`) `bookings.php` uses; `email_reply` and `privacy` are `bookings.php`'s.
- Search sections: the part files mark their own (`fy-` ids: `fy-routes`, `fy-route-<key>`, `fy-ports`, `fy-timetables`, `fy-fares`, `fy-notices`, `fy-your-data` …); the main file only the contact page's two. Never `?view=` (reserved, [[design-template-hotel]]).

## How does it work without new product behaviour?
- Routes and ports are fixed data drawn as cards and pages; timetables are a table per route and season with times as words; fares a table per kind with the figure through `$money()`, never a price engine. A route link carries the route's title, an answer of the Route pick lists.
- Travel notices are a collection ([[collection-fields]]) with a kind, the route as a pick list and an office field `featured`.
- A booking request is a request, never a ticket: `_cf.url_param` prefill, dates with `min_date` today, consent linking `privacy`, office-only status, messages on the record page's Comments widget, `submitter_only` view. Group enquiries alike.
- Not built: availability, online tickets and payment, prices from vehicle length, ship tracking, weather feeds.

## What is the pfy- look?
- `pfy-brand`, `pfy-display`, `pfy-eyebrow`, `pfy-hero`, `pfy-lift`, `pfy-price`, `pfy-quote`, `pfy-stat`, `pfy-time`; Barlow Condensed headings, Source Sans 3 body. No `opacity-*` text or fixed date. Field ids `fy-`.
- Turkish: "feribot", "hat" (route), "iskele" / "liman" (port), "sefer" (sailing), "sefer saatleri", "ücretler", "yaya yolcu", "araçlı yolcu", "kabin", "rezervasyon talebi", "grup talebi", "sefer duyuruları", "Haberler".
- Own-look polishing (its block family, home rebuilt): `plans/design-template-own-looks-order.md`.

## Which tests cover it?
- `design_template_ferry_test.php` (data, shared parts, `_test_fy_page_keys()`) and `…_public_`, `…_notices_`, `…_bookings_`, `…_standard_`, `…_account_test.php`; `…_assembly_test.php` (settings, pages against `_test_fy_page_keys()`, widgets, placeholders, `pfy-` rules, threads, samples, Google reviews).
- In the shared lists of the collection-form (`blog_new`, `notice_new`, `staff_entry`), account-email, submitter-name, consent-privacy (`fy-bk-consent`, `fy-gr-consent`), min-date (`fy-bk-out`, `fy-bk-back`, `fy-gr-date`) and faded-text tests; the url-params test reads every template.
