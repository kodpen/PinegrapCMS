---
name: design-template-library
description: Plan: the Public Library design template (pinegrap-library): catalogue and new arrivals, events and reading clubs, membership and room booking requests, branches and hours, news; ~1,000-line steps
---

## What is it?
- A design template `pinegrap-library` for a public (town or district) library: home, visit (branches with opening hours, getting there, access), the catalogue with new arrivals and a page per title, an item reservation request, events and reading clubs with a page each and a place request, children and teens, digital services, rooms and spaces with a room booking request, joining (a library card request) followed from the member's account, about, FAQ, News (the blog), contact, privacy, sign-in and a staff area. No shop: a public library takes no payment online. Started by a coordinator (`workflow/coordinator-no-plans.md`).
- Built like School and Museum: `pinegrap-library.php` plus the folder `pinegrap-library/` (`parts.php`, `mail.php`, `pages_public.php`, `catalogue.php`, `events.php`, `membership.php`, `rooms.php`, `standard.php`, `account.php`), each page file returns `pages` and `widgets`, kept as `$…_part` in the main file ([[design-template-hotel]]).

## Design
- What it reuses, what it does not build, the look and the Turkish words: [[design-template-library-design]].

## Steps (claim as `[plan:design-template-library] <n> — <topic>`; each waits for the one before it, Working mode 1)
- 1 (done, #7770): `parts.php` (data, pick lists, shared parts, cards, form fields, comment thread, blog parts); `tests/design_template_library_test.php`.

- 2 (done, #7824): `pages_public.php`: home (`home_arrivals`, `home_events`, `home_posts` slots), `visit` and one `branch_<key>` per branch, `children`, `digital`, `spaces`, `about`, `faq`; test.

- 3: `catalogue.php`: the Catalogue collection (`catalogue` by `?format=` and `?audience=`, the `item` page with author, format, shelf and branch, `item_new`), `home_arrivals` (office field `new_arrival`); test.

- 4: `mail.php` (the e-mail builders) and `events.php`: the Events collection (`events` by `?kind=`, `event`, `event_new`, `home_events`) and place requests (`event_booking` with `?event=`, `booking_view`, `my_bookings`, `staff_bookings`, `privacy`, `email_reply`); test.

- 5: `membership.php`: library card requests (`join`, `membership_view`, `my_memberships`, `staff_memberships`) and item reservation requests (`reserve` with `?title=`, `reservation_view`, `my_reservations`, `staff_reservations`); test.

- 6: `rooms.php`: room booking requests (`room_booking` with `?room=`, `room_booking_view`, `my_room_bookings`, `staff_room_bookings`); test.

- 7: `standard.php`: contact, inbox, conversation, newsletter, News (the blog), blog post, help search, error ([[design-templates-standard-pages]]); test.

- 8: `account.php`: sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`, the e-mail pages; test.

- 9: assembly: `pinegrap-library.php` (order, search sections, look, fonts, `plb-` rules, folders, contact group, shared parts), `…_assembly_test.php`, the template in the shared test lists (collection-form, account-email, submitter-name, consent-privacy, faded-text, url-params), `designer/design-template-library.md`; the PR body names the `[NEW]` line; this plan and its design file are deleted.

## Where to start?
- The next free step is the lowest one not marked done. Models: `pinegrap-school/`, `pinegrap-museum/`. `parts.php` names the catalogue `$catalogue_items` (`$item_card`, `$item_facts`); `plb-cover` needs its 2:3 `--bs-aspect-ratio` rule in the assembly. Later tests share `_test_lb_page_keys()` from `tests/design_template_library_test.php`; the pick lists the lists filter on are `$format_titles` (`?format=`), `$audience_titles` (`?audience=`) and `$event_kind_titles` (`?kind=`); the request forms read `?event=`, `?title=` and `?room=`.

## Open questions
- None: everything above reuses shipped features. A step that finds it needs new behaviour asks the architect (Working mode 10).
