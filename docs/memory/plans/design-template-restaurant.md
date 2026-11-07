---
name: design-template-restaurant
description: Plan: the Restaurant design template (pinegrap-restaurant), one chef-led restaurant with menu, reservations, chef's table, events, private dining and gift vouchers, in ~1,000-line steps
---

## What is it?
- A design template `pinegrap-restaurant` for a single chef-led restaurant: home, the menu (dishes as a collection), tasting menus, the wine list, about and the team, table reservation requests the guest follows from the account, the chef's table booked at a free time, events (wine dinners, cooking classes), private dining with enquiries, gift vouchers sold in the shop, FAQ, location, contact, a blog (Kitchen Journal), sign-in and a staff area.
- Not the Café: that is a café chain with catering and no shop ([[design-template-cafe]]).
- Built like Hotel and Gym: `pinegrap-restaurant.php` plus the folder `pinegrap-restaurant/`, each page file returning `pages` and `widgets`, kept as `$…_part` in the main file ([[design-template-hotel]]).

## Design
- What it reuses, what it does not build, the look and the Turkish words: [[design-template-restaurant-design]].

## Steps (claim as `[plan:design-template-restaurant] <n> — <topic>`; each waits for the one before it, Working mode 1)
- 1 (done, #7647): `parts.php`; test.
- 2 (done, #7780): `pages_public.php`; test.
- 3 (done, #7793): `menu.php` (Menu and Events collections, `home_dishes`, `home_events`); test.
- 4 (done, #7883): `mail.php`, `reservations.php` (reservation request pages and e-mails, `book_online`, `privacy`, `email_reply`); test.
- 5 (done, #7972): `private.php` (`private_dining`, `private_enquiry`, `enquiry_view`, `my_enquiries`, `staff_enquiries`, `email_enquiry_new`, `email_enquiry_received`) and `shop.php` (`vouchers` with the voucher card, product, cart, checkout, order, `email_order`, the Gift Vouchers catalog); test.
- 6 (done, #7998): `standard.php`: contact, inbox, conversation, newsletter, blog, blog post, help search, error ([[design-templates-standard-pages]]); test.
- 7 (done, #8018): `account.php`: sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`, the e-mail pages; test.
- 8: assembly: `pinegrap-restaurant.php` (order, search sections, look, fonts, `prt-` rules, folders, contact group, shared parts), `…_assembly_test.php`, the template in the shared test lists (collection-form, account-email, submitter-name, consent-privacy, faded-text, booking-templates, url-params), `designer/design-template-restaurant.md`; the PR body names the `[NEW]` line; this plan and its design file are deleted.

## Where does the next session start?
- Step 8, the assembly. Copy `pinegrap-hotel.php`: include parts, mail, then pages_public, menu, reservations, private, shop, standard, account in one scope, each return kept as `$…_part`; `shop.php` also returns `catalog`.
- Traps: `parts.php` keeps `$rooms`, `$events`, `$vouchers`, `$dishes`, `$tastings`; a part's return under those names would overwrite them. The My Account card is `$account_card`.
- `tests/design_template_restaurant_account_test.php` already checks that the eight parts make exactly `_test_rt_page_keys()` with each page and widget once; the assembly test adds order, search sections, `prt-` rules and the shared lists.
- Contact group `newsletter` (Restaurant Newsletter); look, fonts, order and icon in [[design-template-restaurant-design]].
- PHP 7.1: no trailing comma after a call's last argument. Committer `Erdal Güral`.

## Open questions
- None: everything above reuses shipped features. A step that finds it needs new behaviour asks the architect (Working mode 10).
