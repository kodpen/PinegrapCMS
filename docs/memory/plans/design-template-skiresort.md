---
name: design-template-skiresort
description: Plan: the Ski Resort design template (pinegrap-skiresort): slopes, lifts, conditions, passes and rental, lesson requests, lodging enquiries, dated events; small steps
---

## What is it?
- A template `pinegrap-skiresort` for a mountain ski resort: home, today's conditions, the Slopes and Lifts collections (difficulty, area, open or closed), passes and rental as words and, with the shop, as products, ski school lesson requests by level and age (`?lesson=`), lodging and group enquiries (`?stay=`), events by date, News, newsletter, contact, about, FAQ, sign-in and a staff area. From the coordinators' free-work list.
- Built like Museum and Gallery: `pinegrap-skiresort.php` plus the folder `pinegrap-skiresort/`; each page file returns `pages` and `widgets`, kept as `$…_part` in the main file ([[design-template-museum]]). Parts are cut in two steps (data, then `cards.php`).

## Design
- What it reuses, what it does not build, the look and the Turkish words: [[design-template-skiresort-design]].

## Steps (claim as `[plan:design-template-skiresort] <n> — <topic>`; each waits for the one before it, Working mode 1)
- 1 (done, #7958): `parts.php`: node builders, the resort's data, pick lists, `$money`, `$query`, `$event_on`, page shell and helpers; `tests/design_template_skiresort_test.php`.

- 2 (done, #7986): `cards.php`: shared parts (account bar, notice, header, footer, snow band, staff bar), the record and data cards, form fields, the comment thread, newsletter and blog parts; `tests/design_template_skiresort_cards_test.php`.

- 3: `pages_public.php`: home (`home_slopes`, `home_events`, `home_posts` placed as widgets), `conditions`, `passes`, `rental`, `ski_school`, `lodging`, `about`, `faq`; test.

- 4: `slopes.php`: the Slopes collection (`slopes` by `?difficulty=` and `?area=`, `slope`, `slope_new`, `home_slopes`), the Lifts collection (`lifts`, `lift_new`); test.

- 5: `mail.php` and `lessons.php`: lesson requests (`lesson_request` with `?lesson=`, `?level=`, `?age=`, `lesson_view`, `my_lessons`, `staff_lessons`, `privacy`, `email_reply`, `email_lesson_new`, `email_lesson_received`); test.

- 6: `enquiries.php`: lodging and group enquiries (`group_enquiry` with `?stay=` and `?group=`, `enquiry_view`, `my_enquiries`, `staff_enquiries`, `email_enquiry_new`, `email_enquiry_received`); test.

- 7: `events.php`: the Events collection by date (`events` from today on, `event`, `event_new`, `home_events`); test.

- 8: `shop.php`: the catalog (three groups, SKUs `SKI-PASS-`, `SKI-SEASON-`, `SKI-RENT-<key>`), `buy_passes`, `rental_shop`, product, cart, checkout, order, `email_order`; test.

- 9: `standard.php`: contact, inbox, conversation, newsletter, News (`blog`), blog post, help search, error ([[design-templates-standard-pages]]); test.

- 10: `account.php`: sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`, the account e-mail pages; test.

- 11: assembly: `pinegrap-skiresort.php` (order, search sections, look, fonts, `psk-` rules, folders, shared parts), `…_assembly_test.php`, the template in the shared test lists (collection-form, account-email, submitter-name, consent-privacy, faded-text, custom-form-rules, url-params), `designer/design-template-skiresort.md`; the PR body names the `[NEW]` line; this plan and its design file are deleted.

## Where to start?
- The lowest step not marked done. Models: `pinegrap-museum/`; `pinegrap-restaurant/` for dated events (`$event_on`, `$bind_date`, `current date` filter). `_test_sk_page_keys()` and `_test_sk_shop_keys()` (step 1 test) list every page key. Lists: `?lesson=` `$lesson_choices`, `?level=` `$level_titles`, `?age=` `$age_titles`, `?stay=` `$stay_choices`, `?group=` `$group_kinds`, `?difficulty=` `$difficulty_titles`, `?area=` `$area_titles`. Never `?view=` ([[design-template-hotel]]).

## Open questions
- None: everything above reuses shipped features. A step that finds it needs new behaviour asks the architect (Working mode 10).
