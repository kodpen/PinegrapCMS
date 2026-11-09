---
name: design-template-zoo
description: Zoo and Aquarium template (pinegrap-zoo): a home that opens on the day, zoo- blocks, animals and events as collections, visit requests, tickets/memberships/adoptions shop, pzo- look, tests.
---

## What does it ship?
- Public pages (`home` with `home_animals`, `home_events`, `home_posts`; `today`, `map`, `tickets`, `memberships`, `adopt`, `about`, `faq`); `animals`, `animal`, `animal_new`; visit requests (`visit_request`, `visit_view`, `my_visits`, `staff_visits`, `privacy` and their e-mails); `events`, `event`, `event_new`; shop pages (`buy_tickets`, `adopt_shop`, product, cart, checkout, order); standard pages with conservation News; account and staff pages. All keys: `_test_zoo_page_keys()`.
- Shared: account_bar, notice, header, footer, staff_bar, visit_band, email_header/footer. Contact group `newsletter` (Zoo Newsletter). Built in #8090, #8143, #8170, #8217, #8251, #8262, #8275, #8290, #8297, #8321 and the assembly.

## Why a main file plus a folder?
- `pinegrap-zoo.php` includes `parts.php`, `cards.php`, `mail.php`, then `pages_public.php`, `animals.php`, `visits.php`, `events.php`, `shop.php`, `standard.php`, `account.php` in one scope, each return kept as `$…_part`.
- Trap: `parts.php` keeps `$animals`, `$visits`, `$events`, `$tickets`, `$memberships`, `shop.php` `$catalog`; no part's return is kept under such names (`$animal_part`, `$visit_part`, `$event_part`).

## What makes it its own?
- The home opens on the day: short hero, `zoo-today` second, then the animal spotlight, zone cards, map teaser, conservation band; no figures row, testimonials or FAQ accordion on the home.
- It builds the `zoo-` blocks itself ([[designer-blocks-zoo]]) and ships their rules `pg-zoo-time` and `pg-zoo-pin` in User Styles (`pg_design_template_user_styles(array('zoo-today', 'zoo-map', 'zoo-conservation'), …)`).

## Which shipped features does it reuse? (no new schema, module or behaviour)
- Animals and events are collections ([[collection-fields]]): animals filter by `?habitat=` and `?class=`; events have a `date` counted from the day the template opens (`$event_on`) and list today on. Never `?view=`.
- Today's schedule is words in the `zoo-today` block, the same every day of a season.
- Tickets, memberships and adoptions are one price table in `parts.php` said as words on public pages and sold as products of three groups with the shop (`shippable => false`); only shop pages and `address_book` need the shop.
- A school or group visit is a request: office-only status, messages as comments, `submitter_only` view, visit date `min_date` today, `?visit=`, `?group=`, `?age=` prefill.
- Not built: timed entry slots, a live map or cams, adoption certificates, member pricing, feeding-time alerts, donations beyond products.

## What is the pzo- look?
- `pzo-brand`, `pzo-display`, `pzo-hero` (a primary-colour wash from the lower left), `pzo-mark` (the tilted leaf by a section title), `pzo-price`, `pzo-stat` (tabular figures), `pzo-zone` (habitat card lift, still under reduced motion); field and section ids `zoo-`. Bricolage Grotesque headings, Rubik body. Look `organic`, palette `forest`, order 143 (141 is Podcast's, 145 Planetarium's), icon `bi-tree`. No `opacity-*` on text ([[design-template-text-contrast]]).
- Turkish: "hayvanat bahçesi", "akvaryum", "yaşam alanı", "besleme saati", "bakıcı sohbeti", "sahiplenme", "koruma", "Haberler" for News.

## Which tests cover it?
- `design_template_zoo_test.php` and one test per part file; `…_assembly_test.php` the main file: order and folders, pages in order, shop pages and links, widgets placed once, placeholders, search sections, `pzo-` rules and the shipped `pg-zoo-*` rules, the three comment threads and the reply card, samples and catalog kept, the home's widgets.
- In the shared lists of the collection-form (`animal_new`, `blog_new`, `event_new`, `staff_entry`), account-email, submitter-name, consent-privacy (`zoo-vr-consent`), custom-form-rules (`zoo-vr-date`) and faded-text tests.
