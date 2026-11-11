---
name: design-template-brewery
description: Craft Brewery template (pinegrap-brewery): beers and taproom events as collections, tour booking requests, private tastings booked online, packs and Beer Club terms in the shop, pbw- look, tests.
---

## What does it ship?
- Home (`home_beers`, `home_events`, `home_posts`), `taproom` (hours, tap board and food as page content), `about`, `faq`; `beers` (`?style=`), the `beer` record page with drinkers' notes as comments with stars, `beer_new` (staff); `events` (from today on, `?kind=`), `past_events`, `event`, `event_new`; `tours`, `tour_booking` (`?tour=`), `booking_view`, `my_bookings`, `staff_bookings`, `privacy`, `book_online`, the booking e-mails, `email_reply`; shop pages (`shop` with packs, merch, gift cards; `club` with the Beer Club terms; product, cart, checkout, order, `email_order`) and the club's members' area (`club_home`, `club_releases` in folder `club`); the standard pages with the blog called News; sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`; the account e-mails.
- Built in #7650–#8124 and the assembly; 2.0.0 (#8714) draws the home, taproom and beer page on the Brewery blocks ([[design-template-brewery-pages]]).

## Why a main file plus a folder?
- `pinegrap-brewery.php` includes `parts.php`, `mail.php`, then `pages_public.php`, `beers.php`, `events.php`, `tours.php`, `shop.php`, `standard.php`, `account.php` in one scope, each return kept as `$…_part`; `$beers`, `$events`, `$tours`, `$packs`, `$gift_cards`, `$club_terms` are parts.php's data.

## Which shipped features does it reuse? (no new schema, module or behaviour)
- Beers and taproom events are collections: samples seeded from parts.php, events dated from today on (`$event_on()`), `ticket_url` a plain link (tickets are sold elsewhere).
- Tour bookings are requests as the Hotel's and the Gym's: `?tour=`, date `min_date` today, a required legal-drinking-age box, office-only status, messages on the Comments widget, `submitter_only` view, own and staff lists.
- Private tastings: `book_online` places the booking widget's starter; it and `tour_booking` link each other ([[booking-templates]]).
- Shop: Brewery Shop groups; Beer Club terms carry `membership_days`, so a term opens the `club` folder (access `membership`), as Courses ([[design-template-courses]]). Only shop pages and `address_book` carry `requires => ecommerce`; My Account links the club only with the shop on, shared parts never link shop pages.
- Not built: an age gate, automatic monthly club charges, scheduled club boxes, a live tap board, event ticket sales, tour capacity per slot, licence checks at checkout.

## What is the pbw- look?
- `pbw-abv`, `pbw-brand`, `pbw-date`, `pbw-display`, `pbw-eyebrow`, `pbw-hero`, `pbw-lift`, `pbw-price`, `pbw-quote`; field ids `bw-`. Zilla Slab headings, Work Sans body. Look `organic`, palette `amber`, order 129, icon `bi-cup-straw`. No `opacity-*` on text ([[design-template-text-contrast]]). No fixed date, year or month; seasonal beers name their season in words. Footer, shop and tour booking say the beers are for adults of legal drinking age.
- Turkish: "bira", "bira stili", "tadım salonu" (taproom), "fabrika turu"/"tur", "Bira Kulübü", "etkinlik", "alkol oranı" (ABV), "acılık" (IBU).

## Which tests cover it?
- `design_template_brewery_test.php` (data, shared parts, `_test_bw_page_keys()`, `_test_bw_shop_keys()`) and `…_public_`, `…_collections_`, `…_tours_`, `…_shop_`, `…_standard_`, `…_account_test.php` load the parts; `…_assembly_test.php` the main file: pages in order, shop pages, widgets placed once, folders, search sections, `pbw-` rules, the four comment threads (beer notes checked for settings only: threaded one level deep), samples and products kept.
- In the shared lists of the collection-form (`beer_new`, `blog_new`, `event_new`, `staff_entry`), account-email, submitter-name, consent-privacy (`bw-tb-consent`), custom-form-rules (`bw-tb-date`), faded-text and booking-templates (`tour_booking` ↔ `book_online`) tests.
