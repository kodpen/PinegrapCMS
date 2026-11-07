---
name: design-template-cityhall
description: Plan: the Municipality / City Hall design template (pinegrap-cityhall): services directory, notices and tenders collection, events, council and departments, citizen requests; ~1,000-line steps
---

## What is it?
- A design template `pinegrap-cityhall` for a municipality or city hall: home, a services directory (service areas with a page each, the services of each area as cards), the mayor, the council and its committees, departments with their heads, hours and phones, the city, service points with hours, useful numbers, FAQ, the Notices collection (public notices, tenders, council decisions, vacancies) with a page per notice, the Events collection, citizen requests and complaints followed from the citizen's account, a meeting request with the mayor's office, News (the blog), contact, privacy, sign-in and a staff area. No shop and no payment. Started by a coordinator from the free-work list (`workflow/coordinator-no-plans.md`).
- Built like Construction and School: `pinegrap-cityhall.php` plus the folder `pinegrap-cityhall/` (`parts.php`, `mail.php`, `pages_public.php`, `notices.php`, `requests.php`, `standard.php`, `account.php`), each page file returns `pages` and `widgets`, kept as `$…_part` in the main file ([[design-template-construction]]).

## Design
- What it reuses, what it does not build, the look and the Turkish words: [[design-template-cityhall-design]].

## Steps (claim as `[plan:design-template-cityhall] <n> — <topic>`; each waits for the one before it, Working mode 1)
- 1 (done, #7783): `parts.php` (node builders, the city's data: service areas and services, departments, mayor, council and committees, neighbourhoods, service points, numbers, figures, pick lists; shared parts: account bar, notice strip, header, footer, request band, staff bar; service-area, service, department, council, notice, event and point cards, hours table, form fields, comment thread, blog parts); `tests/design_template_cityhall_test.php`.
- 2 (claimed): `pages_public.php`: home (`home_notices`, `home_events`, `home_posts`), `services` and one `service_<key>` per area, `mayor`, `council`, `departments`, `city`, `service_points`, `numbers`, `faq`; `tests/design_template_cityhall_public_test.php`.
- 3: `notices.php`: the Notices collection (`notices` by `?type=`, the `notice` record page, `notice_new`, `home_notices`) and the Events collection (`events` by `?category=`, `event`, `event_new`, `home_events`); test.
- 4: `mail.php` and `requests.php`: citizen requests (`request` with `?topic=` from the service pages, `request_view`, `my_requests`, `staff_requests`, `privacy`, `email_reply`) and mayor meeting requests (`meet`, `meeting_view`, `my_meetings`, `staff_meetings`); test.
- 5: `standard.php`: contact, inbox, conversation, newsletter, News (the blog), blog post, help search, error ([[design-templates-standard-pages]]); test.
- 6: `account.php`: sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`, the e-mail pages; test.
- 7: assembly: `pinegrap-cityhall.php` (order, search sections, look, fonts, `pcy-` rules, folders, contact group, shared parts), `…_assembly_test.php`, the template in the shared test lists (collection-form, account-email, submitter-name, consent-privacy, faded-text, url-params), `designer/design-template-cityhall.md`; the PR body names the `[NEW]` line; this plan and its design file are deleted.

## Where to start?
- The next free step is the lowest one not marked done. Model files: `pinegrap-construction/`, `pinegrap-school/`. The request form reads `?topic=` (service-area pages); the notice list filters `?type=`, the event list `?category=`. Never use `?view=` (reserved, [[design-template-hotel]]). Later tests share `_test_cy_page_keys()` from `tests/design_template_cityhall_test.php`.

## Open questions
- None: everything above reuses shipped features. A step that finds it needs new behaviour asks the architect (Working mode 10).
