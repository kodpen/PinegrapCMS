---
name: design-template-florist
description: Florist template (pinegrap-florist): bouquets, occasions, subscriptions, event flowers, delivery, Our Work collection, order requests from the account, consultations, shop catalog, pfl- look, tests.
---

## What does it ship?
- The visitor's pages (home with `home_work`, `google_reviews`, `home_posts`; `bouquets`, `occasions`, `subscriptions`, `events`, `delivery`, `care`, `about`, `faq`), Our Work (`work`, `work_item`, `work_new`), the requests (`order_request`, `event_enquiry`, `subscribe`, their view pages, `my_requests`, `staff_requests`, `staff_events`, `consultation`), the shop pages, the standard pages with the Flower Journal ([[design-templates-standard-pages]]), the account and staff pages, the e-mails.
- Shared: account_bar, notice, header, footer, staff_bar, delivery_band, email_header/footer. Order 119, icon `bi-flower1`, look `organic`, palette `coral` (the e-mails' inline rose). Contact group `newsletter` (Florist Newsletter). `catalog`: bouquets in groups by occasion (`FLORIST-BQ-…`), a prepaid subscription bundle per plan (`FLORIST-SUB-…`), vases and extras, gift cards (`shippable => false`). Built in #7648, #7797, #7806, #7912, #7975, #8007, #8026, #8058 and the assembly.

## Why a main file plus a folder?
- As Hotel ([[design-template-hotel]]): `pinegrap-florist.php` includes `parts.php`, `mail.php`, then `pages_public.php`, `work.php`, `orders.php`, `enquiries.php`, `shop.php`, `standard.php`, `account.php` in one scope, each return kept as `$…_part`.
- Trap: `parts.php` keeps `$works`, `shop.php` `$catalog`; no part's return is kept under those names.

## How does it work without new product behaviour?
- Bouquets, occasions, plans, event services, delivery areas and seasons are `parts.php` data; prices are words from `$money()`.
- Our Work is a collection ([[collection-fields]]): event type and season pick lists, `photo_1`-`photo_4`, office field `featured`. Lists filter `?type=` and `?season=` ([[collection-lists]]).
- An order request, an event enquiry and a subscription request are records of their own forms, requests, never sales: choices by `_cf.url_param`, dates with `min_date` today, office-only status, messages on the record page's Comments widget (`request_messages`, `event_messages`, `subscription_messages`), `submitter_only` views; My Requests lists all three, the staff see `staff_requests` (orders and subscriptions) and `staff_events`. A first consultation is booked at a free time with the booking widget's starter on `consultation`, beside the event enquiry ([[booking-templates]]).
- A shop order's delivery date is the checkout's requested arrival date ([[checkout-requested-arrival-date]]). Only the shop pages and `address_book` carry `requires => ecommerce`; shared parts, record pages and My Account never link them.
- Not built (new behaviour): recurring billing, a card message or slot on a shop order line, postcode delivery pricing, a cut-off enforced at checkout, stem stock.

## What is the pfl- look?
- `pfl-brand`, `pfl-display`, `pfl-eyebrow`, `pfl-price` (tabular figures), `pfl-quote`, `pfl-lift`; two fonts (Cormorant Garamond headings, Nunito Sans body). Field ids `fl-`. No `opacity-*` on text; the sample names no fixed date, year or month (seasons and "the day before").
- Turkish: "çiçekçi" for the florist, "özel gün" for an occasion (the key `Occasions` reads "Etkinlikler", so the pages say `Special Occasion`), "kart mesajı", "sipariş talebi", "Çalışmalarımız" for Our Work.

## Which tests cover it?
- `design_template_florist_test.php` and its `…_public_`, `…_work_`, `…_orders_`, `…_enquiries_`, `…_shop_`, `…_standard_`, `…_account_test.php` load the parts; `…_assembly_test.php` the main file (as Wedding's, with five threads). It is in the shared lists of the collection-form (`blog_new`, `staff_entry`, `work_new`), account-email, submitter-name, consent-privacy (`fl-ord-consent`, `fl-evt-consent`, `fl-sub-consent`), faded-text and booking-templates (`event_enquiry`, `consultation`) tests.
