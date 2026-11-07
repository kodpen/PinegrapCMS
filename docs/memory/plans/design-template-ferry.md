---
name: design-template-ferry
description: Plan: the Ferry design template (pinegrap-ferry): routes and ports, timetables and fares as words, booking requests, travel notices, onboard services; ~1,000-line steps
---

## What is it?
- A design template `pinegrap-ferry` for a ferry line: home, routes (a page per route with its crossing time, ports, sailings and fares as words), ports (check-in, parking, how to get there), timetables (a table of sailings per route and season), fares (foot passengers, cars, motorcycles, campervans and vans, bicycles, cabins), onboard services (café and restaurant, lounges, cabins, pets, accessibility, Wi-Fi), travelling with us (check-in times, luggage, what to bring), about, FAQ, booking requests (passengers and vehicles) followed from the traveller's account, the Travel Notices collection (disruptions, timetable changes, weather, port works) with a page per notice and a list by route, News (the blog), contact, privacy, sign-in and a staff area. No shop: the booking office confirms and takes payment. Started by a coordinator from the free-work list (`workflow/coordinator-no-plans.md`).
- Built like Marina and Campsite: `pinegrap-ferry.php` plus the folder `pinegrap-ferry/` (see the steps), each page file returns `pages` and `widgets`, kept as `$…_part` in the main file ([[design-template-construction]]).

## Design
- What it reuses, what it does not build, the look and the Turkish words: [[design-template-ferry-design]].

## Steps (claim as `[plan:design-template-ferry] <n> — <topic>`; each waits for the one before it, Working mode 1)
- 1: `parts.php` (node builders, the line's data and pick lists: routes, ports, seasons, sailings, fare kinds and fares, onboard services, travel tips, figures, quotes; shared parts, cards, timetable and fare tables, form fields, comment thread, blog parts); `tests/design_template_ferry_test.php`.
- 2: `pages_public.php`: home (`home_notices`, `home_posts`), `routes` and one `route_<key>` per route, `ports`, `timetables`, `fares`, `onboard`, `travel`, `about`, `faq`; test.
- 3: `notices.php`: the Travel Notices collection (`notices` by `?route=`, the `notice` record page, `notice_new`), `home_notices`; test.
- 4: `mail.php` and `bookings.php`: booking requests (`booking_request` with `?route=` and `?fare=`; `booking_view`, `my_bookings`, `staff_bookings`, `privacy`, `email_reply`) and group enquiries (`group_enquiry` with `?route=`; `group_view`, `my_groups`, `staff_groups`); test.
- 5: `standard.php`: contact, inbox, conversation, newsletter, News (the blog), blog post, help search, error ([[design-templates-standard-pages]]); test.
- 6: `account.php`: sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`, the e-mail pages; test.
- 7: assembly: `pinegrap-ferry.php` (order, search sections, look, fonts, `pfy-` rules, folders, contact group, shared parts), `…_assembly_test.php`, the template in the shared test lists (collection-form, account-email, submitter-name, consent-privacy, faded-text, url-params), `designer/design-template-ferry.md`; the PR body names the `[NEW]` line; this plan and its design file are deleted.

## Where to start?
- The next free step is the lowest one not marked done. Models: `pinegrap-marina/`, `pinegrap-campsite/`. The booking request reads `?route=` (route pages, timetables) and `?fare=` (fares page); the group enquiry reads `?route=`; the notice list filters `?route=`. Route keys are fixed data, so a link carries the route's key and the form's pick list shows its title; never a filter on another record's title without that page's own `?param=`. Never use `?view=` (reserved, [[design-template-hotel]]). Later tests share `_test_fy_page_keys()` from `tests/design_template_ferry_test.php`.

## Open questions
- None: everything above reuses shipped features. A step that finds it needs new behaviour asks the architect (Working mode 10).
