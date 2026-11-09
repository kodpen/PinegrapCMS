---
name: design-template-restaurant
description: Restaurant template (pinegrap-restaurant): menu and events as collections, reservation requests, chef's table, private dining enquiries, gift vouchers in the shop, prt- look, tests.
---

## What does it ship?
- Home (`home_dishes`, `home_events`, `home_posts`, `google_reviews` under Guest Stories), `tasting`, `wine`, `about`, `faq`, `location`; `menu` (a tab per course), `dish`, `dish_new`; `events`, `event`, `event_new`; `reserve` (`?tasting=`, `?event=`, `?time=`, `?occasion=`), `reservation_view`, `my_reservations`, `staff_reservations`, `book_online` (the chef's table), `privacy`, `email_reply`; `private_dining`, `private_enquiry` (`?room=`, `?kind=`), `enquiry_view`, `my_enquiries`, `staff_enquiries`; shop pages (`vouchers`, product, cart, checkout, order, `email_order`) with `requires => ecommerce`; the standard pages with the Kitchen Journal, account and staff pages. The full list is `_test_rt_page_keys()`.
- Catalog: the Gift Vouchers group (shop.php's `catalog`); only shop pages and `address_book` need the shop.
- Shared: account_bar, notice, header, footer, staff_bar, reserve_band, email_header/footer. Order 109, icon `bi-egg-fried`, look `elegant`, palette `burgundy` (e-mail `#9f1239`). Contact group `newsletter` (Restaurant Newsletter). Built in #7647 to #8018 and the assembly.

## Why a main file plus a folder?
- As Hotel ([[design-template-hotel]]): `pinegrap-restaurant.php` includes `parts.php`, `mail.php`, then `pages_public`, `menu`, `reservations`, `private`, `shop`, `standard`, `account` in one scope, returns kept as `$…_part` (`$dishes`, `$tastings`, `$rooms`, `$events`, `$vouchers` are parts.php's data; the My Account card is `$account_card`).
- `reservations.php` defines the request pieces (`$detail_widget`, `$request_list`, `$request_messages`, `$not_before_today`) that `private.php` reuses, plus `privacy` and `email_reply`.

## How does it work without new product behaviour?
- Menu: a collection ([[collection-fields]]) with course, price as words, diet tags, badge, photo and office field `featured` (the home list); the menu tabs filter by the course's fixed words, no `?course=`.
- Events: a collection; dates counted from the day the template opens (`$event_on`), a card books through `reserve?event=…`.
- A reservation and a private dining enquiry are requests, never a confirmed table: date with `min_date` today, consent linking `privacy`, office-only status, messages on the record page's Comments widget, `submitter_only` view, the account's and staff's lists.
- The chef's table: `book_online` places the booking widget (`'tree' => 'starter'`) beside a link to `reserve` ([[booking-templates]]).
- Not built: live availability, deposits, food ordering, voucher redemption.

## What is the prt- look?
- `prt-brand`, `prt-display`, `prt-eyebrow`, `prt-hero` (a palette wash), `prt-lift`, `prt-price` (tabular figures), `prt-quote`; Cormorant Garamond headings, Jost body. Field ids `rt-`. No fixed date in the sample; no `opacity-*` on text.
- Turkish: "rezervasyon", "menü", "tadım menüsü", "şarap listesi", "hediye çeki", "özel yemek", "şefin masası", "Mutfak Günlüğü".

## Which tests cover it?
- `design_template_restaurant_test.php` (data, shared parts, `_test_rt_page_keys()`, `_test_rt_nodes()`) and `…_public_`, `…_menu_`, `…_reservations_`, `…_private_`, `…_standard_`, `…_account_test.php`; `…_assembly_test.php`: page order equal to the plan's pages, widgets placed once, placeholders, `requires`, search sections, `prt-` rules, four comment threads and the reply card, samples, Google reviews.
- In the shared lists of the collection-form (`blog_new`, `dish_new`, `event_new`, `staff_entry`), account-email, submitter-name, consent-privacy (`rt-rs-consent`, `rt-pd-consent`), faded-text, booking-templates (`reserve`, `book_online`) and custom-form-rules (`rt-rs-date`, `rt-pd-date`) tests; the url-params test covers every template on its own.
