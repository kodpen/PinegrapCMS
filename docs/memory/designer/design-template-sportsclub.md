---
name: design-template-sportsclub
description: Sports Club template (pinegrap-sportsclub): teams as data, players, matches and events as collections, academy and trial requests, kit, tickets and memberships, psp- look, tests.
---

## What does it ship?
- Home (`home_fixtures`, `home_results`, `home_events`, `home_posts`, `google_reviews`), `teams` and one `team_<key>` per team, `club`, `ground`, `academy`, `sponsors`, `faq`; `squad` (`?team=`), `player`, `player_new`; `fixtures` and `results` (`?team=`), `match`, `match_new`; `academy_register` (`?team=`), `registration_view`, `my_registrations` (lists trials too), `staff_registrations`; `trial` (`?team=`, `?position=`), `trial_view`, `staff_trials`; `privacy`, `email_reply`; `events` (`?kind=`), `event`, `event_new`; `sponsor_enquiry` (`?package=`), `staff_sponsors`; the shop pages and `members`; the standard pages with Club News, account and staff pages. The full list is `_test_sp_page_keys()`.
- Catalog: Kit (shipped), Season Tickets (`shippable => false`), Memberships (`membership_days`, open the `members` folder, access `membership`). Only the shop pages and `address_book` carry `requires => ecommerce`; shared parts and record pages never link them.
- Shared: account_bar, notice, header, footer, staff_bar, join_band, email_header/footer. Order 113, icon `bi-trophy`, look `bold`, palette `forest` (e-mail `#3f6212`). Contact group `newsletter`. Built in #7727 to #8040 and the assembly PR.

## Why a main file plus a folder?
- As Gym and Library: `pinegrap-sportsclub.php` includes `parts.php`, `squad.php` (its `$sp_*` list helpers serve the later files), `mail.php`, then `pages_public`, `matches`, `registrations`, `events`, `shop`, `standard`, `account` in one scope; returns are kept as `$…_part` (`$teams`, `$players`, `$matches`, `$events`, `$kit`, `$memberships`, `$season_tickets` are parts.php's data).
- Page files prefix their helpers (`rg_`, `sh_`, `st_`, `ac_`).

## How does it work without new product behaviour?
- Teams are fixed data; players a collection (team, position, shirt number, photo, office `featured`).
- Matches: pick lists, a date written from today on opening (`$match_on()`), score as words, report; `fixtures` from today, `results` before.
- An academy registration and a trial request are requests, never enrolments: `_cf.url_param` choices, consent linking `privacy`, office-only status, messages on the record page's Comments widget, `submitter_only` view, My Registrations and the staff lists. The sponsorship enquiry has the staff list only.
- Not built: league tables, live scores, player statistics, seat maps, match-day ticket sales, fee payments.

## What is the psp- look?
- `psp-brand`, `psp-display` (condensed capitals), `psp-eyebrow`, `psp-score` (tabular figures for scores and shirt numbers), `psp-crest`, `psp-hero` (a diagonal shirt-stripe wash), `psp-lift` (still under reduced motion); Barlow Condensed headings, Inter body. Field ids `sp-`. No fixed date in the sample; no `opacity-*` on text.
- Its pages were built before the own-look rule ([[design-template-own-look]]); its polishing (`sportsclub-1`, `-2`) joins `plans/design-template-own-looks-order.md`.
- Turkish: "kulüp", "takım", "kadro", "oyuncu", "fikstür", "maç", "sonuç", "altyapı", "seçme", "kombine bilet", "forma", "üyelik", "Kulüp Haberleri".

## Which tests cover it?
- `design_template_sportsclub_test.php` (data, shared parts, `_test_sp_page_keys()`) and `…_public_`, `…_collections_`, `…_registrations_`, `…_events_`, `…_shop_`, `…_standard_`, `…_account_test.php`; `…_assembly_test.php`: page order, widgets placed once, placeholders, `requires`, search sections, `psp-` rules, four comment threads and the reply card, samples, Google reviews.
- In the shared lists of the collection-form (`blog_new`, `event_new`, `match_new`, `player_new`, `staff_entry`), account-email, submitter-name, consent-privacy (`sp-rg-`, `sp-tr-`, `sp-sp-consent`) and faded-text tests; the url-params test covers every template on its own.
