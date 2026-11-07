---
name: design-template-library
description: Public Library template (pinegrap-library): branches as data, catalogue and events as collections, card, reservation, place and room requests, no shop, plb- look, tests.
---

## What does it ship?
- Home (`home_arrivals`, `home_events`, `home_posts`, `google_reviews`), `visit` and one `branch_<key>` per branch, `children`, `digital`, `spaces`, `about`, `faq`; `catalogue` (`?format=`, `?audience=`), `item`, `item_new`; `events` (`?kind=`), `event`, `event_new`; four requests, each with its form page, `_view`, `my_…`, `staff_…` and two e-mail pages: place requests (`event_booking`), library cards (`join`), title reservations (`reserve`, `?title=`), room bookings (`room_booking`, `?room=`); `privacy`, the standard pages with News, account and staff pages. The full list is `_test_lb_page_keys()`.
- No catalog: a public library takes no payment online; only `address_book` carries `requires => ecommerce`.
- Shared: account_bar, notice, header, footer, staff_bar, join_band, email_header/footer. Order 103, icon `bi-book`, look `elegant`, palette `forest` (e-mail `#3f6212`). Contact group `newsletter`. Built in #7770 to #7945 and the assembly PR.

## Why a main file plus a folder?
- As School and Museum ([[design-template-museum]]): `pinegrap-library.php` includes `parts.php`, `mail.php`, then `pages_public`, `catalogue`, `events`, `membership`, `rooms`, `standard`, `account` in one scope, returns kept as `$…_part` (`$branches`, `$catalogue_items`, `$events`, `$rooms`, `$staff` are parts.php's data).
- `catalogue.php` defines `$lb_filter`, `$lb_page_link`, `$lb_yes_no`, `$lb_filter_bar`; `events.php` the request pieces (`$request_view`, `$detail_widget`, `$request_list`, `$request_messages`, `$staff_list_page`) the later parts reuse, plus `privacy` and `email_reply`.

## How does it work without new product behaviour?
- Branches and rooms are fixed data: hours per day as words, what each offers, a page per branch.
- The catalogue is a collection ([[collection-fields]]): author, format and audience pick lists, genre, shelf mark, branch, availability as words, `photo_1` cover, office field `new_arrival` (the home list).
- Events: kind pick list, branch, when and age group as words, `featured`.
- A card, a reservation, a place and a room booking are requests, never a loan or a confirmed booking: the title, event or room by `_cf.url_param`, consent linking `privacy`, office-only status, messages on the record page's Comments widget, `submitter_only` view, the account's and staff's lists. The card request takes proof of address as a file in `private`; the room booking's date has `min_date` today.
- Not built: loans, renewals, due dates, fines, a live catalogue search against an ILS, hold queues, room capacity, eBook lending.

## What is the plb- look?
- `plb-brand`, `plb-display`, `plb-eyebrow`, `plb-hero`, `plb-lift`, `plb-stat`, `plb-hours` (tabular figures), `plb-quote`, `plb-cover` (a 2:3 `ratio`); Literata headings, Source Sans 3 body. Field ids `lib-`. No fixed date in the sample; no `opacity-*` on text.
- Turkish: "kütüphane", "şube", "katalog", "yeni gelenler", "ayırtma", "okuma kulübü", "etkinlik", "kütüphane kartı", "salon" / "oda", "Haberler".

## Which tests cover it?
- `design_template_library_test.php` (data, shared parts, `_test_lb_page_keys()`, `_test_lb_nodes()`) and `…_public_`, `…_catalogue_`, `…_events_`, `…_membership_`, `…_rooms_`, `…_standard_`, `…_account_test.php`; `…_assembly_test.php`: page order, widgets placed once, placeholders, `requires`, search sections, `plb-` rules, six comment threads and the reply card, samples, Google reviews.
- In the shared lists of the collection-form (`blog_new`, `event_new`, `item_new`, `staff_entry`), account-email, submitter-name, consent-privacy (`lib-eb-`, `lib-mb-`, `lib-rs-`, `lib-rb-consent`), faded-text and custom-form-rules (`lib-rb-date`) tests; the url-params test covers every template on its own.
