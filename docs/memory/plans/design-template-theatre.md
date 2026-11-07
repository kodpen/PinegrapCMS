---
name: design-template-theatre
description: Plan: the Theatre design template (pinegrap-theatre): season of productions, performance dates, tickets, friends membership, learning, venue hire, news; ~1,000-line steps
---

## What is it?
- A design template `pinegrap-theatre` for a theatre or performing-arts venue: home, the season (productions now playing, coming soon and past) with a page each, performance dates from today with a page each, your visit (box office hours, getting there, access, the auditorium as words), learning (youth theatre, workshops and school group requests followed from the teacher's account), venue hire with an enquiry followed from the hirer's account, support and friends as words, about, FAQ, news (the blog), contact, privacy, sign-in and a staff area; tickets and friends membership in the shop. Started by a coordinator (`workflow/coordinator-no-plans.md`).
- Built like Museum and Music: `pinegrap-theatre.php` plus the folder `pinegrap-theatre/` (`parts.php`, `mail.php`, `pages_public.php`, `productions.php`, `performances.php`, `hire.php`, `learning.php`, `shop.php`, `standard.php`, `account.php`), each page file returns `pages` and `widgets`, kept as `$…_part` in the main file ([[design-template-hotel]]).

## Design
- What it reuses, what it does not build, the look and the Turkish words: [[design-template-theatre-design]].

## Steps (claim as `[plan:design-template-theatre] <n> — <topic>`; each waits for the one before it, Working mode 1)
- 1 (done, #7792): `parts.php`, `tests/design_template_theatre_test.php`.

- 2 (done, #7849): `pages_public.php`.

- 3 (done, #7859): `productions.php`.

- 4 (done, #7871): `performances.php`.

- 5 (done, #7897): `mail.php`, `hire.php`.

- 6 (done, #7957): `learning.php`.

- 7 (done, #7962): `shop.php`.

- 8 (done, #7989): `standard.php`.

- 9 (done, #8014): `account.php`.

- 10: assembly: `pinegrap-theatre.php` (order, search sections, look, fonts, `pth-` rules, folders, contact group, shared parts), `…_assembly_test.php`, the template in the shared test lists (collection-form, account-email, submitter-name, consent-privacy, faded-text, url-params), `designer/design-template-theatre.md`; the PR body names `[NEW] Added the Theatre design template`; this plan and its design file are deleted.

## Where to start?
- Step 10 (assembly) is next: every part file exists and `tests/design_template_theatre_account_test.php` already checks that the parts together carry exactly `_test_th_page_keys()`. The shop's catalog adds a third group (`vouchers`, Gift Vouchers) beside Tickets and Friends; the staff area places `staff_hire_new` (hire.php's request list).
- The next free step is the lowest one not marked done. Models: `pinegrap-museum/` (requests, exhibitions with a state), `pinegrap-music/` (dates from today, `$show_on`). Later tests share `_test_th_page_keys()` from `tests/design_template_theatre_test.php`; `hire.php` holds the request pieces `learning.php` reuses; the pick lists the lists filter on are `$production_status_titles` (`?status=`), `$genre_titles` (`?genre=`) and `$access_titles` (`?access=`); `?production=` takes a code (no link bar). The field names the cards bind: [[design-template-theatre-design]].

## Open questions
- None: everything above reuses shipped features. A step that finds it needs new behaviour asks the architect (Working mode 10).
