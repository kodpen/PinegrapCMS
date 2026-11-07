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
- 7: `account.php`: sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`, the e-mail pages; test.
- 8: assembly: `pinegrap-restaurant.php` (order, search sections, look, fonts, `prt-` rules, folders, contact group, shared parts), `…_assembly_test.php`, the template in the shared test lists (collection-form, account-email, submitter-name, consent-privacy, faded-text, booking-templates, url-params), `designer/design-template-restaurant.md`; the PR body names the `[NEW]` line; this plan and its design file are deleted.

## Where does the next session start?
- Step 7. Copy `pinegrap-hotel/account.php`; `blog_new` is the Kitchen Journal's post form (fields `title`, `summary`, `content`, `cover_image`, read by `home_posts`, `blog_list` and `blog_post` of `standard.php`); My Account links My Reservations, My Enquiries and the inbox.
- In scope from earlier parts: `reservations.php` request pieces (`$request_page`, `$request_view`, `$staff_list_page`, `$request_list`, `$request_detail`, `$request_messages`, `$contact_fields`, `$guest_name`, `$mail_sender`, `$mail_reference`, `$mail_new_account`, `$not_before_today`); `menu.php` (`$bind_date`, `$page_link`, `$checkboxes`, `$not_found`, `$record_toolbar`); `private.php` (`$enquiry_row`, `$mail_enquiry`); `shop.php` (`$catalog`, `$shop_page`, `$voucher_card`); `standard.php` (`$contact_hours`, `$help_topic`). The template file (step 8) includes parts, mail, menu, reservations, private, shop, standard, then account.
- Avoid keys whose shared Turkish is wrong here (tests list them): `Starters`, `Course`, `Glass`, `Dish`, `Find Us`, `Choose a course…`; a time label is `Hour` ("Saat"), `Time` reads "Zaman".
- Test helper prefixes taken: `_test_rt_`, `_test_rtp_` (public), `_test_rtm_`, `_test_rtr_`, `_test_rtpd_` (private and shop), `_test_rts_` (standard). `_test_rt_page_keys()` lists every page key; add step 7's e-mail pages there.
- PHP 7.1: no trailing comma after a call's last argument. Committer `Erdal Güral`.

## Open questions
- None: everything above reuses shipped features. A step that finds it needs new behaviour asks the architect (Working mode 10).
