---
name: design-template-music
description: Music Artist template (pinegrap-music): shows and releases as collections, press kit with a public press folder, booking enquiries, merch and gift cards in the shop, pmu- look, tests.
---

## What does it ship?
- Home (`home_shows`, `home_releases`, `home_posts`), `about`, `videos`, `faq`; `discography`, `release`, `release_new`; `tour`, `past_shows`, `show`, `show_new`; `press`, `press_file_new`, `booking`, `booking_view`, `my_bookings`, `staff_bookings`, `privacy`, `email_reply`, `email_booking_*`; shop pages (`merch`, product, cart, checkout, order, `email_order`); the standard pages, blog called News, newsletter titled Mailing List ([[design-templates-standard-pages]]); account and staff pages; the e-mails.
- Shared: account_bar, notice, header, footer, staff_bar, list_band, email_header/footer. Order 91, icon `bi-music-note-beamed`, look `bold`, palette `midnight` (e-mail blue `#1e3a8a`). Folders root, public, registration, private and a public `press` folder for the press files. Contact group `newsletter`. `catalog` from `merch.php`: Merch group (`MUSIC-*` SKUs) and gift cards. Built in #7544–#7773 and #7784.

## Why a main file plus a folder?
- As Travel ([[design-template-travel]]): `pinegrap-music.php` includes `parts.php`, `mail.php`, then `pages_public`, `releases`, `shows`, `press`, `merch`, `standard`, `account` in one scope, returns kept as `$…_part`.
- Trap: `parts.php` keeps the samples in `$releases`, `$shows`, `$merch`, `$gift_cards`, `$members`, `$videos`; no part may return into those names.
- Most search sections are marked where built (`$searchable()`); the main file marks only the contact page's Contact Details and Inbox Note.

## How does it work without new product behaviour?
- Releases are a collection ([[collection-fields]]): format pick list, `year` as words, cover, track list as long text (one line per track), credits, hidden `listen_url` / `video_url`, office field `featured`. Fans' words are the release page's comments (`release_comments`, newest first, one answer level).
- Shows are a collection: date kind written from today on opening (`$show_on()`), country and ticket state pick lists, `ticket_url`; the tour lists dates from today on ([[collection-lists]]).
- A booking enquiry is a request, never a confirmed show: event, city, date with `min_date` today, capacity, fee band, office-only status, messages on the record page's Comments widget, `submitter_only` views, My Enquiries and the staff list. Only shop pages and `address_book` carry `requires => ecommerce`; shared parts never link them.
- Not built: ticket sales, audio players, downloads, ticket-service feeds, paid fan clubs.

## What is the pmu- look?
- `pmu-brand`, `pmu-display`, `pmu-eyebrow`, `pmu-hero` (glow on the dark home band), `pmu-date` (tabular figures), `pmu-tracks` (lines kept), `pmu-quote`, `pmu-lift`; Bebas Neue headings (one weight, never bold), Inter body. Field ids `mu-`.
- Turkish: "konser", "turne", "diskografi", "parça", "basın kiti", "e-posta listesi", "konser talebi". Shared keys with another meaning are avoided: `Tour` (Tur), `Tracks`, `Release` (Kaldır), `Bookings` and `Booking` (Randevu); the template uses `Tour Dates`, `Track Listing`, `Release Title`, `Show Bookings`, `Concert bookings`.

## Which tests cover it?
- `design_template_music_test.php` (data, shared parts, `_test_mu_page_keys()`, `_test_mu_shop_keys()`) and `…_public_`, `…_collections_`, `…_press_`, `…_merch_`, `…_standard_`, `…_account_test.php`; `…_assembly_test.php`: offered without the shop, catalog, press folder, pages in order, widgets once and placed, placeholders, shop-only `requires`, contact search sections, `pmu-` rules, five comment widgets (release comments not drawn: the shared draw goes two answers deep), reply e-mail card, samples kept, the home's lists.
- In the shared lists of the collection-form (`blog_new`, `press_file_new`, `release_new`, `show_new`, `staff_entry`), account-email, submitter-name, consent-privacy (`mu-bk-consent`) and faded-text tests.
