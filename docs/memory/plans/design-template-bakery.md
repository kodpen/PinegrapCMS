---
name: design-template-bakery
description: Plan: the Bakery design template (pinegrap-bakery): daily bake, cakes, a pickup shop, custom cake orders, wholesale enquiries, baking classes; ~1,000-line steps
---

## What is it?
- A template for an artisan bakery and patisserie: the daily bake, celebration cakes, ordering and pickup, wholesale, visit, about, FAQ; cake orders and wholesale enquiries followed from the account; baking classes with a place request; a pickup shop; contact, Journal (the blog), sign-in, staff area. Café (`pinegrap-cafe`) is a café with no online shop.
- Built like Gym, Museum and School: `pinegrap-bakery.php` plus the folder `pinegrap-bakery/` (`parts.php`, `mail.php`, `pages_public.php`, `classes.php`, `requests.php`, `wholesale.php`, `shop.php`, `standard.php`, `account.php`), each page file returns `pages` and `widgets`, kept as `$…_part` in the main file ([[design-template-hotel]]).

## Design
- What it reuses, what it does not build, the look and the Turkish words: [[design-template-bakery-design]].

## Steps (claim as `[plan:design-template-bakery] <n> — <topic>`; each waits for the one before it, Working mode 1)
- 1 (done, #7651): `parts.php` (builders, the bakery's data and pick lists, shared parts, bake, cake, class and testimonial cards, form fields, comment thread, blog parts); `tests/design_template_bakery_test.php`.

- 2 (done, #7895): `pages_public.php`: home (`home_classes`, `google_reviews`, `home_posts` placed), `bakes` (the daily bake by category), `cakes`, `how_to_order` (pickup days and times as words), `wholesale`, `visit`, `about`, `faq`; `tests/design_template_bakery_public_test.php`.

- 3 (done, #7916): `classes.php`: the Baking Classes collection (`classes` by `?level=`, the `class` page, `class_new`, `home_classes`); test.

- 4 (done, #7941): `mail.php` (e-mail builders) and `requests.php` (cake orders, class place requests, `privacy`, `email_reply`, the request e-mails; shared `$bk_…` page and list builders for step 5); test.

- 5 (done, #8164): `wholesale.php`: wholesale enquiries (`wholesale_enquiry` with `?range=`, `enquiry_view`, `my_enquiries`, `staff_enquiries`, the enquiry e-mails); test.

- 6: `shop.php`: `shop`, product, cart, checkout, order, `email_order`, the catalog (Breads, Pastries, Cakes, Gift Boxes and Baking Classes groups; class places `shippable => false`); test.

- 7 (done, #8161): `standard.php`: contact, inbox, conversation, newsletter, Journal (the blog), blog post, help search, error ([[design-templates-standard-pages]]); test.

- 8 (done, #8182): `account.php`: sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`, the e-mail pages; test.

- 9: assembly: `pinegrap-bakery.php` (order, search sections, look, fonts, `pbk-` rules, folders, contact group, shared parts), `…_assembly_test.php`, the template in the shared test lists (collection-form, account-email, submitter-name, consent-privacy, faded-text, url-params), `designer/design-template-bakery.md`; the PR body names the `[NEW]` line; this plan and its design file are deleted.

## Where to start?
- The next free step is the lowest one not marked done. Model files: `pinegrap-gym/` (requests, classes), `pinegrap-cafe.php` (menu words, `min_date`, `required_when`), `pinegrap-market/shop.php` (catalog groups). Later tests `require_once` `tests/design_template_bakery_test.php` for `_test_bk_page_keys()` / `_test_bk_shop_keys()`.
- `classes.php` defines `$bk_filter`, `$bk_page_link`, `$bk_featured_choices` for later parts; the class page sends `?class=` (`name`) to `class_booking`, whose Class field reads it (`$class_names`).
- Step 9: include `mail.php` before `standard.php` and `account.php`; `home_posts` is in `standard.php`, `login_region` in `account.php`.
- Check a shared key's Turkish before reuse (`Classes` reads "Sınıflar"). PHP 7.1: no trailing comma after a call's last argument.

## Open questions
- None: everything above reuses shipped features. A step that finds it needs new behaviour asks the architect (Working mode 10).
