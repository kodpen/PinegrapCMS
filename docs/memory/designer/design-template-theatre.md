---
name: design-template-theatre
description: Theatre template (pinegrap-theatre): productions and performances as collections, hire, school group and youth place requests, tickets, friends and vouchers in the shop, pth- look, tests.
---

## What does it ship?
- Home (`home_productions`, `home_performances`, `home_posts`, `google_reviews`), `visit`, `support`, `about`, `faq`; `season` (`?genre=`, `?status=`), `production`, `production_new`; `whats_on` (from today; `?production=` a code, `?access=`), `past_performances`, `performance`, `performance_new`; three requests, each with its form page, `_view`, `my_…`, `staff_…` and two e-mail pages: venue hire (`hire`, `?space=`), school groups (`school_group`, `?workshop=`), youth theatre places (`youth_place`, `?group=`); `privacy`, `email_reply`; the shop pages (`tickets`, `friends`, …); the standard pages with News; account and staff pages. The full list is `_test_th_page_keys()`.
- Catalog: Tickets (bands), Friends (tiers) and Gift Vouchers groups, SKUs `THEATRE-TICKET-<key>`, `THEATRE-FRIEND-<key>`, `THEATRE-GIFT-<key>`, `shippable => false`. Prices come from one table each through `$money()`; only the shop pages and `address_book` carry `requires => ecommerce`, the public pages say the prices as words.
- Shared: account_bar, notice, header, footer, staff_bar, box_office_band, email_header/footer. Order 105 (99 was Campsite's), icon `bi-ticket-perforated`, look `luxe`, palette `crimson` (e-mail `#b91c1c`). Contact group `newsletter`. Built in #7792 to #8014 and the assembly PR.

## Why a main file plus a folder?
- As Museum ([[design-template-museum]]): `pinegrap-theatre.php` includes `parts.php`, `mail.php`, then `pages_public`, `productions`, `performances`, `hire`, `learning`, `shop`, `standard`, `account` in one scope, returns kept as `$…_part` (`$productions`, `$performances`, `$spaces`, `$tickets`, `$tiers` are parts.php's data, `$catalog` is shop.php's).
- `hire.php` holds the request pieces `learning.php` reuses (`$request_view`, `$my_requests_page`, `$staff_list_page`), plus `privacy` and `email_reply`.

## How does it work without new product behaviour?
- Productions are a collection ([[collection-fields]]) with genre and state pick lists, the run as words, credits, cast; audiences' words are comments (`production_comments`, one level). Performances reference the production (`__ref`), `perf_date` counted from today (`$perf_on`), access and ticket-state pick lists.
- Hire, school group and youth place are requests: consent linking `privacy`, office-only status, messages on the record page's Comments widget, `submitter_only` view, the account's and staff's lists; dates have `min_date` today.
- Not built: seating, a ticket tied to a date, capacity, member pricing, Gift Aid.

## What is the pth- look?
- `pth-brand`, `pth-display`, `pth-eyebrow`, `pth-hero`, `pth-lift` (off under reduced motion), `pth-stat`, `pth-price`, `pth-date` (a fixed column in the performance rows), `pth-quote`; Playfair Display headings, Karla body. Field ids `th-`. No fixed date or year in the sample; no `opacity-*` on text.
- Turkish: "yapım", "temsil", "sezon", "Tiyatro Dostları", "salon kiralama", "gençlik tiyatrosu"; it says "The Season", "Theatre Stage", "Run Dates", "The Production" because the bare keys mean other things.
- Still to come: its own block family and a rebuilt home (`plans/design-template-own-looks-order.md`).

## Which tests cover it?
- `design_template_theatre_test.php` (data, shared parts, `_test_th_page_keys()`), one `…_<part>_test.php` per part; `…_assembly_test.php`: page order, widgets placed once, placeholders, `requires`, search sections, `pth-` rules, six comment threads and the reply card, samples, Google reviews.
- In the shared lists of the collection-form (`blog_new`, `performance_new`, `production_new`, `staff_entry`), account-email, submitter-name, consent-privacy (`th-hire-`, `th-sg-`, `th-yp-consent`), faded-text and custom-form-rules (`th-hire-date`, `th-sg-date`) tests; the url-params test covers every template on its own.
