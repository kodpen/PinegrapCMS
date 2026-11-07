---
name: design-template-photo
description: Photography Studio template (pinegrap-photo): galleries collection, shoot booking requests, private proofing folders and selections, deposits and vouchers, pph- look, tests.
---

## What does it ship?
- Home (featured galleries `home_galleries`, `home_posts`, `google_reviews`), `packages`, `experience` (how a shoot works), `about`, `faq`; `galleries` (`?category=`), the `gallery` record page, `gallery_new` (staff), `book_online` (studio mini sessions on the booking widget); `book` (`?package=`, `?category=`), `booking_view`, `my_bookings`, `staff_bookings`, `privacy`, `email_reply`; `client_area`, the private sample folder's `proofing_sample`, `selection` (`?gallery=`), `selection_view`, `my_selections`, `staff_selections`; shop pages (`vouchers`, product, cart, checkout, order, `email_order`); the standard pages with the blog called Photo Journal and the help page called Client Help ([[design-templates-standard-pages]]); sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`; the e-mail pages.
- Built in #7498, #7522, #7545, #7572, #7603, #7621, #7644, #7698 and the assembly PR.

## Why a main file plus a folder?
- As Hotel ([[design-template-hotel]]): `pinegrap-photo.php` includes `parts.php`, `mail.php`, then `pages_public.php`, `galleries.php`, `bookings.php`, `clients.php`, `shop.php`, `standard.php`, `account.php` in one scope, each return kept as `$…_part`.

## How does it work without new product behaviour?
- A gallery is a record of the Galleries collection: category pick list, location, story, `cover` and `photo_1`-`photo_6` image kinds, office field `featured`.
- A booking is a request, never a reservation: office-only status, messages on the record page's Comments widget, `submitter_only` views, a staff list.
- Proofing: a client's gallery is a page in a `private` folder; the studio duplicates the sample folder per client and gives that client the folder. The client sends frame numbers with the Proof Selections form.
- Shop: a deposit per package and gift vouchers, `shippable => false`. Only shop pages and `address_book` carry `requires => ecommerce`.
- Not built (new behaviour): favourites by clicking a photo, watermarks, per-client gallery lists without the folder, print ordering, live availability on location, a balance invoice.

## What is the pph- look?
- `pph-brand`, `pph-display`, `pph-eyebrow`, `pph-price`, `pph-quote`, `pph-lift`, `pph-hero`; DM Serif Display headings, Manrope body; look `minimal`, palette `steel`, order 85, icon `bi-camera`. Field ids `ph-`. No `opacity-*` on text; no fixed year or month in the sample.
- Turkish: "çekim" (shoot), "galeri", "paket", "seçim" (proof selection), "kapora" (deposit); the tests check these words.

## Which tests cover it?
- `design_template_photo_test.php` (data, shared parts) and `…_public_`, `…_galleries_`, `…_bookings_`, `…_clients_`, `…_shop_`, `…_standard_`, `…_account_test.php` load the parts; `…_assembly_test.php` the main file. It is in the shared lists of the collection-form, account-email, submitter-name, consent-privacy, faded-text and booking-templates tests.
