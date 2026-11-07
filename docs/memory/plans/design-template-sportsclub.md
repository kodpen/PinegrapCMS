---
name: design-template-sportsclub
description: Plan: the Sports Club design template (pinegrap-sportsclub): teams, squads, fixtures and results, academy and trials, membership, season tickets, kit and sponsors; ~1,000-line steps
---

## What is it?
- A design template `pinegrap-sportsclub` for an amateur football or multi-sport club: teams with a page each, squads, fixtures and results, club news, events, youth academy registrations and trial requests, membership, season tickets, kit shop, sponsors, ground, honours, FAQ, contact, sign-in and a staff area. Started by a coordinator (`workflow/coordinator-no-plans.md`).
- Built like Gym, Museum and Music: `pinegrap-sportsclub.php` plus the folder `pinegrap-sportsclub/` (`parts.php`, `mail.php`, `pages_public.php`, `squad.php`, `matches.php`, `registrations.php`, `events.php`, `shop.php`, `standard.php`, `account.php`), each page file returns `pages` and `widgets`, kept as `$…_part` in the main file ([[design-template-hotel]]).

## Design
- What it reuses, what it does not build, the look and the Turkish words: [[design-template-sportsclub-design]].

## Steps (claim as `[plan:design-template-sportsclub] <n> — <topic>`; each waits for the one before it, Working mode 1)
- 1 (done, #7727): `parts.php` (builders, data, pick lists, shared parts, cards, fields); `tests/design_template_sportsclub_test.php`.

- 2 (done, #7907): `pages_public.php`: home (`home_fixtures`, `home_results`, `home_posts` placed), `teams` and one `team_<key>` per team, `club` (history and honours), `ground`, `academy`, `sponsors`, `faq`; `tests/design_template_sportsclub_public_test.php`.

- 3 (done, #7920): `squad.php` (with the list helpers `$sp_*`) and `matches.php`: the Players collection (`squad` by `?team=`, `player`, `player_new`) and the Matches collection (`fixtures` from today on and `results` before today, both by `?team=`, the `match` page, `match_new`, `home_fixtures`, `home_results`); test.

- 4 (done, #7930): `mail.php` (e-mail builders, `$comment_thread`) and `registrations.php`: academy registrations (`academy_register` ?team=, `registration_view`, `my_registrations` also listing trials, `staff_registrations`), trial requests (`trial` ?team= ?position=, `trial_view`, `staff_trials`), `privacy`, `email_reply`; test.

- 5 (done, #7961): `events.php`: the Club Events collection (`events` by `?kind=`, `event`, `event_new`, `home_events`) and the sponsorship enquiry (`sponsor_enquiry` with `?package=`, `staff_sponsors`); home places `home_events`; test.

- 6 (done, #7990): `shop.php`: `kit_shop`, `season_tickets`, `membership`, product, cart, checkout, order, `email_order`, the catalog (Kit, Season Tickets with `shippable => false`, Membership with `membership_days`), the members' area (`members`, folder `members` with access `membership`); test.

- 7 (done, #8012): `standard.php`: contact, inbox, conversation, newsletter, Club News (the blog), blog post, help search, error ([[design-templates-standard-pages]]); test.

- 8: `account.php`: sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`, the e-mail pages; test.

- 9: assembly: `pinegrap-sportsclub.php` (order, search sections, look, fonts, `psp-` rules, folders, contact group, shared parts), `…_assembly_test.php`, the template in the shared test lists (collection-form, account-email, submitter-name, consent-privacy, faded-text, url-params), `designer/design-template-sportsclub.md`; the PR body names the `[NEW]` line; this plan and its design file are deleted.

## Where to start?
- The next free step is the lowest one not marked done. Include `squad.php` right after `parts.php` (its `$sp_*` helpers). Models: `pinegrap-gym/`, `pinegrap-music/` (dated lists), `pinegrap-school/`. Later tests share `_test_sp_page_keys()` from `tests/design_template_sportsclub_test.php`.
- PHP 7.1: no trailing comma after a call's last argument. Committer: `Erdal Güral` (branch guard).

## Open questions
- None: everything above reuses shipped features. A step that finds it needs new behaviour asks the architect (Working mode 10).
