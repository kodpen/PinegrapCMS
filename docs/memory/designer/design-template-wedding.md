---
name: design-template-wedding
description: Wedding template (pinegrap-wedding): packages, spaces, Real Weddings collection, enquiries from the account, venue visits by the booking widget, deposits and gift cards, pwd- look, tests.
---

## What does it ship?
- Home (packages, spaces, `home_weddings`, testimonials with `google_reviews`, `home_posts`), `about`, `services`, `packages`, `venue`, `gallery`, `timeline`, `faq`; `weddings`, the `wedding` record page, `wedding_new` (staff); `enquiry` (`?package=`, `?service=`, `?space=`), `enquiry_view`, `my_enquiries`, `staff_enquiries`, `privacy`, `email_reply`, `book_visit`; shop pages (`shop`, product, cart, checkout, order, `email_order`); the standard pages with the blog called Wedding Journal ([[design-templates-standard-pages]]); sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`; the e-mail pages.
- Shared: account_bar, notice, header, footer, staff_bar, date_band, email_header/footer. Order 87, icon `bi-balloon-heart`, look `luxe`, palette `sage` (the e-mails' inline greens). Contact group `newsletter` (Wedding Newsletter). `catalog`: the Deposits and Gift Cards group, a date-hold deposit per `$packages` entry and a product per `$gift_cards` entry. Built in #7503, #7585, #7616, #7634, #7681, #7704, #7726 and #7759; version 2.0.0 puts home and timeline on the Wedding blocks ([[design-template-wedding-pages]]).

## Why a main file plus a folder?
- As Hotel ([[design-template-hotel]]): `pinegrap-wedding.php` includes `parts.php`, `mail.php`, then `pages_public.php`, `weddings.php`, `enquiries.php`, `shop.php`, `standard.php`, `account.php` in one scope, each return kept as `$…_part`.
- Trap: `parts.php` keeps the sample weddings in `$weddings`; the Real Weddings part is kept as `$wedding_part`, never `$weddings`.

## How does it work without new product behaviour?
- A Real Wedding is a record of the Real Weddings form, a collection ([[collection-fields]]): couple, space and season pick lists, `guests` number kind, `photo_1`-`photo_4` image kinds, suppliers as long text (one line each), office field `featured`. Lists filter `?space=` and `?season=` ([[collection-lists]]).
- An enquiry is a request, never a booking of the date: a record of Wedding Enquiries (choices by `_cf.url_param`, a wished date with `min_date` today, guests, budget band), office-only status, messages on the record page's Comments widget (`enquiry_messages`), `submitter_only` view, My Enquiries, the staff list. A venue visit or first consultation is booked at a free time with the booking widget's starter on `book_visit`, beside the enquiry ([[booking-templates]]).
- Only the shop pages and `address_book` carry `requires => ecommerce`; shared parts, record pages and My Account never link them.
- Not built (new behaviour): a date calendar, RSVPs, seating plans, supplier portals, a budget calculator, a balance invoice.

## What is the pwd- look?
- `pwd-brand`, `pwd-display`, `pwd-eyebrow`, `pwd-price` (tabular figures), `pwd-quote`, `pwd-lift`; two fonts (Marcellus headings, Lato body). Field ids `wd-`. No `opacity-*` on text; the sample names no fixed date, year or month (seasons and "twelve months before").
- Turkish: "düğün" for a wedding, "nikâh" only for the ceremony, "organizasyon" for planning, "mekân" for the venue, "tanışma görüşmesi" for a consultation, "mekân gezisi" for a venue visit, "tarih tutma kaporası" for the deposit, "gerçek düğünler" for Real Weddings.

## Which tests cover it?
- `design_template_wedding_test.php` and its `…_public_`, `…_weddings_`, `…_enquiries_`, `…_shop_`, `…_standard_`, `…_account_test.php` load the parts; `…_assembly_test.php` the main file (as Hotel's: pages in order, shop-only pages, widgets placed once, placeholders, `pwd-` rules, three threads, samples kept). It is in the shared lists of the collection-form (`blog_new`, `staff_entry`, `wedding_new`), account-email, submitter-name, consent-privacy (`wd-enq-consent`), faded-text and booking-templates (`enquiry`, `book_visit`) tests.
