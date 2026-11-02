---
name: design-template-events
description: Events and Conferences template (pinegrap-events): its parts folder, events and registrations as form records, tickets as shop products, what is not built, the pev- look, tests.
---

## What does it ship?
- Home, programme (by day and track), speakers, venue (travel, hotels), about (sponsors); events, `event_view`, `registration`, `registration_view`, `my_registrations`, `privacy`; tickets, product, cart, checkout, order; contact, inbox, conversation, newsletter, blog, blog post, help-center search, error; sign-in and My Account pages, `staff_directory`; `staff`, `event_new`, `blog_new`, `staff_entry`, the staff registrations list in `private`; the e-mail pages.
- Shared: account_bar, notice, header, footer, staff_bar, register_band, email_header/footer. Order 65, icon `bi-calendar2-event`, look `modern-soft`, palette `aurora`, generic gallery drawing. Contact group `newsletter` (Events Newsletter). Built in #4595, #4612, #4633, #4645, #4686 and the assembly PR.

## Why a main file plus a folder?
- `pg_design_templates()` offers every `includes/design_templates/*.php`; the subfolder is not read as a template. `pinegrap-events.php` includes `parts.php` (builders, the conference's data, shared parts), `mail.php`, then `pages_public.php`, `events.php`, `tickets.php`, `standard.php`, `account.php` in one scope. A change to a page goes in its part; the main file holds the order, the search map, the look and the template keys.

## How do events and registration work?
- Events are records of the Events form (the Café's way): samples dated by `$event_on()` when the template opens, listed from today on, each on `event_view`; no fixed date anywhere, and the conference names no year (its programme is by day).
- A registration is a Registrations form record: the event comes from the Register button's address (`_cf.url_param`), auto-registration gives a visitor an account, the status is office-use-only (Registered, Confirmed, Waitlisted, Cancelled, Attended), messages are the Comments widget on `registration_view`.

## How do tickets work without the shop?
- The passes are products of a Tickets catalog group (`'catalog'` from `tickets.php`, `shippable => false`). The template has no `requires`: only the listing and the product, cart, checkout, order pages and `email_order` carry `requires => ecommerce`. The tickets page always stands, with what each pass includes and the registration form, so no link is dead without the shop.

## Where do the ticket prices come from? (1.0.3, #4822)
- One table, `$pass_prices` in `parts.php` (USD, EUR, GBP, TRY), feeds both the catalog products and the pass cards; `$money()` writes a price the way the shop does (`pg_money_text()`, base currency's figure and symbol, USD's figure for an unlisted currency, as `_pg_tpl_catalog()` chooses). The paid sample events use `$money()` too, and the conference reads `Passes from %s` with the Day Pass price. Before, the cards were fixed `lang('$149')` keys: "$149" on the card, "₺5.999,00" in the shop.

## What is deliberately not built?
- Session collections, seat limits and QR check-in: new product behaviour.

## Why pev- and not pe-?
- Bootstrap owns `pe-*` (padding-end; `pe-0` is used in the parts). The template's classes are `pev-brand` (header name), `pev-eyebrow` (section header line), `pev-hero` (home's first section), `pev-lift` (speaker and event cards), with two fonts (Space Grotesk, Inter) in `assets`.

## Where are its Turkish words described?
- [[design-template-events-words]].

## Which tests cover it?
- `design_template_events_test.php` (data, public pages, events, registration, tickets), `…_standard_test.php`, `…_account_test.php` load the parts; `…_assembly_test.php` the main file: offered, pages in order, widgets once and all placed, placeholders name pages and folders, catalog, contact search sections, `pev-` classes equal rules. It is in the shared lists of the collection-form, account-e-mail, submitter-name and consent-privacy tests (no date field, so not the form-rules list).
