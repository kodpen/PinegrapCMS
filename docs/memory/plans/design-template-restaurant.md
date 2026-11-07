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
- 1 (done, #7647): `parts.php` (builders, the restaurant's data and pick lists, shared parts, cards, form fields, comment thread, blog parts); test.
- 2 (done, #7780): `pages_public.php`: home, `tasting`, `wine`, `about`, `faq`, `location`; test.
- 3 (done, #7793): `menu.php`: the Menu collection (`menu` with a tab per course, `dish`, `dish_new`), the Events form (`events`, `event`, `event_new`), `home_dishes`, `home_events`; test.
- 4 (done, #7883): `mail.php` (the e-mail builders) and `reservations.php`: table reservation requests (`reserve`, `reservation_view`, `my_reservations`, `staff_reservations`, `privacy`, `email_reply`, `email_reservation_new`, `email_reservation_received`) and `book_online` (the chef's table on the booking widget's starter); test.
- 5: `private.php` (`private_dining`, `private_enquiry`, `enquiry_view`, `my_enquiries`, `staff_enquiries`, `email_enquiry_new`, `email_enquiry_received`) and `shop.php` (`vouchers` with the voucher card, product, cart, checkout, order, `email_order`, the Gift Vouchers catalog); test.
- 6: `standard.php`: contact, inbox, conversation, newsletter, blog, blog post, help search, error ([[design-templates-standard-pages]]); test.
- 7: `account.php`: sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`, the e-mail pages; test.
- 8: assembly: `pinegrap-restaurant.php` (order, search sections, look, fonts, `prt-` rules, folders, contact group, shared parts), `…_assembly_test.php`, the template in the shared test lists (collection-form, account-email, submitter-name, consent-privacy, faded-text, booking-templates, url-params), `designer/design-template-restaurant.md`; the PR body names the `[NEW]` line; this plan and its design file are deleted.

## Where does the next session start?
- Step 5. Copy the enquiry pages from `pinegrap-hotel/events.php`; `?room=` (`$room_choices`) and `?kind=` (`$enquiry_kinds`) by `_cf.url_param`, statuses `$enquiry_statuses`, reply by `$contact_choices`.
- `reservations.php` defines the request pieces step 5 reuses: `$request_aside`, `$request_page`, `$request_view`, `$staff_list_page`, `$request_row`, `$detail`, `$bound`, `$guest_name`, `$detail_widget`, `$request_list`, `$request_detail`, `$request_messages`, `$contact_fields`, `$mail_sender`, `$mail_reference`, `$mail_new_account`, `$not_before_today`; it also ships `privacy`, `email_reply` and the `email_comment` widget. The template file (step 8) includes parts, mail, menu, reservations, then private.
- `menu.php` defines `$bind_date`, `$page_link`, `$checkboxes`, `$not_found`, `$record_toolbar`.
- Avoid keys whose shared Turkish is wrong here (tests list them): `Starters`, `Course`, `Glass`, `Dish`, `Find Us`, `Choose a course…`; a time label is `Hour` ("Saat"), `Time` reads "Zaman".
- PHP 7.1: no trailing comma after a call's last argument. Committer `Erdal Güral`.

## Open questions
- None: everything above reuses shipped features. A step that finds it needs new behaviour asks the architect (Working mode 10).
