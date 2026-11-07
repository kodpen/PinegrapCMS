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
- 1 (done, #7794): `parts.php`; `tests/design_template_marina_test.php`.
- 2 (done, #7851): `pages_public.php` (home places `home_charters`, `home_events`, `home_posts`); test.
- 3 (done, #7863): `charters.php`; test.
- 4 (done, #7880): `mail.php`, `requests.php` (berth requests, charter enquiries, `privacy`, `email_reply`); test.
- 5 (done, #7943): `events.php` (Events collection, `home_events`, event entries with a text `event` field reading `?event=`); test `_test_mne_load()`.
- 6: `standard.php`: contact, inbox, conversation, newsletter, News (the blog), blog post, help search, error ([[design-templates-standard-pages]]); test.
- 7: `account.php`: sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`, the e-mail pages; test.
- 8: assembly: `pinegrap-marina.php` (order, search sections, look, fonts, `pmn-` rules, folders, contact group, shared parts), `…_assembly_test.php`, the template in the shared test lists (collection-form, account-email, submitter-name, consent-privacy, faded-text, url-params), `designer/design-template-marina.md`; the PR body names the `[NEW]` line; this plan and its design file are deleted.

## Where to start?
- The next free step is the lowest one not marked done. Models: `pinegrap-construction/`, `pinegrap-school/`. The berth request reads `?kind=` (berth-kind pages) and `?season=` (rates page); the charter enquiry reads `?boat=`; the entry request reads `?event=`; the boat list filters `?type=`, the event list `?kind=`. Never use `?view=` (reserved, [[design-template-hotel]]). Later tests share `_test_mn_page_keys()` from `tests/design_template_marina_test.php`. `charters.php` defines `$mn_filter`, `$mn_featured_choices`, `$mn_page_link`, `$mn_filter_bar` (list page key first) and `$mn_number` for the events; their tests and the main file include it first. `requests.php` (after `mail.php`, `charters.php`) defines the `$mn_…` record pieces the entries reuse (rows, record page, lists, messages, mail lines, form fields and end, page shells); test loader `_test_mnr_load()`.

## Open questions
- None: everything above reuses shipped features. A step that finds it needs new behaviour asks the architect (Working mode 10).
