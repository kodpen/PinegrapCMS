---
name: design-template-museum
description: Plan: the Museum / Art Gallery design template (pinegrap-museum): exhibitions, highlights, visit, tickets and membership, events, school visits; ~1,000-line steps
---

## What is it?
- A design template `pinegrap-museum` for a museum or art gallery: home, visit (opening hours, admission as words, getting there, access, facilities), exhibitions (current, upcoming, past) with a page each, collection highlights by department with a page per object, events and talks with a page each and a place request, learning and school visit requests followed from the teacher's account, support and membership as words, about, FAQ, news (the blog), contact, privacy, sign-in and a staff area; admission tickets and membership in the shop; a timed entry slot on `book_online`. Started by a coordinator (`workflow/coordinator-no-plans.md`).
- Built like School, Photo and Travel: `pinegrap-museum.php` plus the folder `pinegrap-museum/` (`parts.php`, `mail.php`, `pages_public.php`, `exhibitions.php`, `objects.php`, `events.php`, `learning.php`, `shop.php`, `standard.php`, `account.php`), each page file returns `pages` and `widgets`, kept as `$…_part` in the main file ([[design-template-hotel]]).

## Design
- What it reuses, what it does not build, the look and the Turkish words: [[design-template-museum-design]].

## Steps (claim as `[plan:design-template-museum] <n> — <topic>`; each waits for the one before it, Working mode 1)
- 1 (done, #7626): `parts.php` (builders, the museum's data and pick lists, the shared parts, the cards, form fields, comment thread, blog parts); `tests/design_template_museum_test.php`.

- 2 (done, #7657): `pages_public.php`: home (`home_exhibitions`, `home_events`, `home_posts`), `visit`, `support` (membership and giving as words), `about`, `faq`; `tests/design_template_museum_public_test.php`.

- 3: `exhibitions.php`: the Exhibitions collection (`exhibitions` by `?status=`, the `exhibition` page, `exhibition_new`), `home_exhibitions`; test.

- 4: `objects.php`: the Collection Highlights collection (`collection` by `?department=`, the `object` page, `object_new`); test.

- 5: `mail.php` (the e-mail builders) and `events.php`: the Events collection (`events` by `?kind=`, `event`, `event_new`, `home_events`) and place requests (`event_booking` with `?event=`, `booking_view`, `my_bookings`, `staff_bookings`, `privacy`, `email_reply`); test.

- 6: `learning.php`: `learning` (sessions by age), school visit requests (`school_visit` with `?session=`, `school_visit_view`, `my_school_visits`, `staff_school_visits`) and `book_online` (booking widget starter for timed entry, [[booking-templates]]); test.

- 7: `shop.php`: `tickets`, `membership`, product, cart, checkout, order, `email_order`, the catalog (Admission and Membership groups, `shippable => false`); test.

- 8: `standard.php`: contact, inbox, conversation, newsletter, News (the blog), blog post, help search, error ([[design-templates-standard-pages]]); test.

- 9: `account.php`: sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`, the e-mail pages; test.

- 10: assembly: `pinegrap-museum.php` (order, search sections, look, fonts, `pmz-` rules, folders, contact group, shared parts), `…_assembly_test.php`, the template in the shared test lists (collection-form, account-email, submitter-name, consent-privacy, faded-text, booking-templates, url-params), `designer/design-template-museum.md`; the PR body names the `[NEW]` line; this plan and its design file are deleted.

## Where to start?
- The next free step is the lowest one not marked done. Model files: `pinegrap-school/`, `pinegrap-photo/`. Later tests share `_test_mz_page_keys()` from `tests/design_template_museum_test.php`; the pick lists the lists filter on are `$exhibition_statuses` (`?status=`), `$department_titles` (`?department=`) and `$event_kind_titles` (`?kind=`).

## Open questions
- None: everything above reuses shipped features. A step that finds it needs new behaviour asks the architect (Working mode 10).
