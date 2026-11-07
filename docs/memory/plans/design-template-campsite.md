---
name: design-template-campsite
description: Plan: the Campsite / Holiday Park design template (pinegrap-campsite): pitches and cabins collection, facilities, activities, stay requests, seasonal prices as words; ~1,000-line steps
---

## What is it?
- A design template `pinegrap-campsite` for a campsite or holiday park: home, places to stay (tent pitches, touring pitches for caravans and motorhomes, glamping tents, cabins and lodges) with a page per kind, the Pitches and Cabins collection with a page per unit, facilities, activities and the area, seasons and prices as words, location and getting here, park rules, about, FAQ, stay requests followed from the guest's account, group and event enquiries, News (the blog), contact, privacy, sign-in and a staff area. No shop: the park takes no payment online. Not `pinegrap-hotel` (rooms in one building, gift vouchers). Started by a coordinator from the free-work list (`workflow/coordinator-no-plans.md`).
- Built like Construction and School: `pinegrap-campsite.php` plus the folder `pinegrap-campsite/` (`parts.php`, `mail.php`, `pages_public.php`, `units.php`, `stays.php`, `standard.php`, `account.php`), each page file returns `pages` and `widgets`, kept as `$…_part` in the main file ([[design-template-construction]]).

## Design
- What it reuses, what it does not build, the look and the Turkish words: [[design-template-campsite-design]].

## Steps (claim as `[plan:design-template-campsite] <n> — <topic>`; each waits for the one before it, Working mode 1)
- 1: `parts.php` (node builders, the park's data: kinds of stay, sample pitches and cabins, facilities, activities, the area, seasons with prices as words, getting here, park rules, figures, guests' words, pick lists; shared parts: account bar, notice, header, footer, stay band, staff bar; stay-kind, unit, facility, activity and testimonial cards, season table, form fields, comment thread, blog parts); `tests/design_template_campsite_test.php`.
- 2: `pages_public.php`: home (`home_units`, `home_posts`), `stays` and one `stay_<key>` per kind, `facilities`, `activities`, `prices`, `location`, `rules`, `about`, `faq`; `tests/design_template_campsite_public_test.php`.
- 3: `units.php`: the Pitches and Cabins collection (`units` by `?kind=`, the `unit` record page, `unit_new`), `home_units`; test.
- 4: `mail.php` and `stays.php`: stay requests (`book` with `?unit=`, `?kind=`, `?season=`; `request_view`, `my_requests`, `staff_requests`, `privacy`, `email_reply`) and group enquiries (`groups`, `group_enquiry`, `enquiry_view`, `my_enquiries`, `staff_enquiries`); test.
- 5: `standard.php`: contact, inbox, conversation, newsletter, News (the blog), blog post, help search, error ([[design-templates-standard-pages]]); test.
- 6: `account.php`: sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`, the e-mail pages; test.
- 7: assembly: `pinegrap-campsite.php` (order, search sections, look, fonts, `pcp-` rules, folders, contact group, shared parts), `…_assembly_test.php`, the template in the shared test lists (collection-form, account-email, submitter-name, consent-privacy, faded-text, url-params), `designer/design-template-campsite.md`; the PR body names the `[NEW]` line; this plan and its design file are deleted.

## Where to start?
- The next free step is the lowest one not marked done. Model files: `pinegrap-construction/`, `pinegrap-school/`, `pinegrap-hotel/`. The stay request reads `?kind=` (stay-kind pages), `?unit=` (unit pages) and `?season=` (prices page); the unit list filters `?kind=`. Never use `?view=` (reserved, [[design-template-hotel]]). Later tests share `_test_cp_page_keys()` from `tests/design_template_campsite_test.php`.

## Open questions
- None: everything above reuses shipped features. A step that finds it needs new behaviour asks the architect (Working mode 10).
