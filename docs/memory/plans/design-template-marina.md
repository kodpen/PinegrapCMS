---
name: design-template-marina
description: Plan: the Marina design template (pinegrap-marina): berths and rates as words, berth requests, Charter Boats, services, events; ~1,000-line steps
---

## What is it?
- A design template `pinegrap-marina` for a marina or yacht harbour: home, berths and moorings (visitor berths, seasonal berths, annual berths, swinging moorings, dry stack) with a page per kind and rates as words, harbour services (fuel, pump-out, boatyard and crane lift, chandlery, laundry, Wi-Fi), facilities, arriving by sea (approach, VHF channel, tides and depths as words) and by land, harbour rules, about, FAQ, visitor berth requests followed from the skipper's account, the Charter Boats collection with a page per boat and charter enquiries, events (regattas, open days, courses) with entry requests, News (the blog), contact, privacy, sign-in and a staff area. No shop: the harbour office takes payment on arrival. Started by a coordinator from the free-work list (`workflow/coordinator-no-plans.md`).
- Built like Construction and Campsite: `pinegrap-marina.php` plus the folder `pinegrap-marina/` (see the steps), each page file returns `pages` and `widgets`, kept as `$…_part` in the main file ([[design-template-construction]]).

## Design
- What it reuses, what it does not build, the look and the Turkish words: [[design-template-marina-design]].

## Steps (claim as `[plan:design-template-marina] <n> — <topic>`; each waits for the one before it, Working mode 1)
- 1 (done, #7794): `parts.php` (node builders, the harbour's data and pick lists, shared parts, cards, rate table, form fields, comment thread, blog parts); `tests/design_template_marina_test.php`.
- 2 (done, #7851): `pages_public.php`: home (`home_charters`, `home_events`, `home_posts`), `berths` and one `berth_<key>` per kind, `rates`, `services`, `facilities`, `arriving`, `rules`, `about`, `faq`; test.
- 3: `charters.php`: the Charter Boats collection (`charters` by `?type=`, the `boat` record page, `boat_new`), `home_charters`; test.
- 4: `mail.php` and `requests.php`: visitor berth requests (`berth_request` with `?kind=`, `?season=`; `request_view`, `my_requests`, `staff_requests`, `privacy`, `email_reply`) and charter enquiries (`charter_enquiry` with `?boat=`; `enquiry_view`, `my_enquiries`, `staff_enquiries`); test.
- 5: `events.php`: the Events collection (`events` by `?kind=`, `event`, `event_new`, `home_events`) and entry requests (`event_entry` with `?event=`, `entry_view`, `my_entries`, `staff_entries`); test.
- 6: `standard.php`: contact, inbox, conversation, newsletter, News (the blog), blog post, help search, error ([[design-templates-standard-pages]]); test.
- 7: `account.php`: sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`, the e-mail pages; test.
- 8: assembly: `pinegrap-marina.php` (order, search sections, look, fonts, `pmn-` rules, folders, contact group, shared parts), `…_assembly_test.php`, the template in the shared test lists (collection-form, account-email, submitter-name, consent-privacy, faded-text, url-params), `designer/design-template-marina.md`; the PR body names the `[NEW]` line; this plan and its design file are deleted.

## Where to start?
- The next free step is the lowest one not marked done. Models: `pinegrap-construction/`, `pinegrap-school/`. The berth request reads `?kind=` (berth-kind pages) and `?season=` (rates page); the charter enquiry reads `?boat=`; the entry request reads `?event=`; the boat list filters `?type=`, the event list `?kind=`. Never use `?view=` (reserved, [[design-template-hotel]]). Later tests share `_test_mn_page_keys()` from `tests/design_template_marina_test.php`.

## Open questions
- None: everything above reuses shipped features. A step that finds it needs new behaviour asks the architect (Working mode 10).
