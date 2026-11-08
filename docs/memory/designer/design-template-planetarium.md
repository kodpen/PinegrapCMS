---
name: design-template-planetarium
description: Planetarium template (pinegrap-planetarium): dome shows and telescope nights as dated collections, school visit requests, tickets and memberships, planetarium- blocks, ppm- look, tests.
---

## What does it ship?
- Public pages (`home` opening on the dark `planetarium-tonight` section, then `home_shows`, `home_nights`, the moon strip, `home_posts`; `tonight`, `visit`, `tickets`, `memberships`, `observatory`, `faq`); `shows`, `show`, `show_new`; `nights`, `night`, `night_new`; school visits (`visit_request`, `visit_view`, `my_visits`, `staff_visits`, `privacy`) with their e-mails; shop pages; standard pages with News; account and staff pages. All keys: `_test_planetarium_page_keys()`.
- Shared: account_bar, notice, header, footer, staff_bar, email_header/footer. Contact group `newsletter` (Planetarium Newsletter). Folders root, public, registration, private. Built in #8172 (blocks), #8216, #8249, #8263, #8277, #8285, #8294, #8304, #8305, #8319 and the assembly.

## Why a main file plus a folder?
- `pinegrap-planetarium.php` includes `parts.php`, `cards.php`, `pages_public.php`, `shows.php`, `nights.php`, `mail.php`, `visits.php`, `shop.php`, `standard.php`, `account.php` in one scope, each return kept as `$…_part`.
- Trap: `parts.php` keeps `$shows`, `$nights`, `$tickets`, `$memberships`, `$visits`, `shop.php` `$catalog`; no part's return is kept under such names.

## Which shipped features does it reuse? (no new schema, module or behaviour)
- Dome shows and telescope nights are collections dated from the day the template opens (`$show_on`), listed from today on; shows filter by `?audience=` ([[collection-lists]]). A night's status (On, Called Off, Moved) is an office field the staff set on the day, never a weather feed.
- Tonight's sky, the objects and the moon phases are words in the `planetarium-` blocks ([[designer-blocks-planetarium]]); their `pg-sky-` rules ship in the template's User Styles with the `ppm-` rules.
- A school visit is a request, never a booking: `?visit=`, `?grade=`, date `min_date` today, office-only status, messages as comments, `submitter_only` view.
- Tickets (`PLM-TICKET-`) and memberships (`PLM-MEMBER-`) are words on the public pages and products with the shop on, `shippable => false`; only shop pages and `address_book` need the shop.
- Not built: seat maps, live weather, computed moon phases, a telescope camera, member pricing in the cart, push alerts.

## What is the ppm- look?
- `ppm-brand`, `ppm-card` (rises under the pointer, still with reduced motion), `ppm-display`, `ppm-eyebrow`, `ppm-hero` (a glow from the top), `ppm-price`, `ppm-rule`, `ppm-time` (tabular figures); field ids `plm-`. Sora headings, IBM Plex Sans body. Look `minimal`, palette `midnight`, order 145 (141 is Podcast's, 147 claimed by Bike Shop), icon `bi-stars`. Night sections `data-bs-theme="dark"` with `bg-body text-body`. No `opacity-*` on text ([[design-template-text-contrast]]).
- Turkish: "planetaryum", "gözlemevi", "kubbe gösterisi", "seans", "teleskop gecesi", "gökbilimci", "Ay evresi", "okul ziyareti", "Haberler".

## Which tests cover it?
- `design_template_planetarium_test.php` and one test per part file load the parts; `…_assembly_test.php` the main file: pages in order, shop pages and links, widgets placed once, folders, search sections, `ppm-` and `pg-sky-` rules, the three comment threads and the reply card, samples kept, the home's first section.
- In the shared lists of the collection-form (`blog_new`, `night_new`, `show_new`, `staff_entry`), account-email, submitter-name, consent-privacy (`plm-vr-consent`), custom-form-rules (`plm-vr-date`) and faded-text tests.
