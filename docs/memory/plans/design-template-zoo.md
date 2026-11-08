---
name: design-template-zoo
description: Plan: the Zoo and Aquarium design template (pinegrap-zoo): animals by habitat, feeding times, park map, tickets, adoptions, visit requests, events; small steps
---

## What is it?
- `pinegrap-zoo`, a zoo with an aquarium: home, today (feeding times, keeper talks), park map, Animals by habitat, tickets, memberships and adoptions (words; products with the shop), school and group visit requests, events by date, conservation news (blog), newsletter, contact, about, FAQ, sign-in, staff area.
- File layout like Ski Resort (main file plus folder `pinegrap-zoo/`, one file per step, `$…_part`; [[design-template-museum]]); never its look.

## What makes it unlike the shipped templates?
- Composition: the home opens on the day: a short hero, `zoo-today` second, then the animal spotlight, zone cards, map teaser, conservation band. No figures row, testimonials, ask band or FAQ accordion on the home.
- Signature sections (family `zoo-`, step 1): the day's schedule with the next item marked, the animal spotlight with its status scale, the numbered park map, the conservation projects.
- Motion: schedule rows and map zones rise on view, the spotlight photo scales in; nothing moves by itself (`props._fx` only).
- Type: Bricolage Grotesque headings, Rubik body; no other template uses either for headings.

## Design
- What it reuses, what it does not build, the look and the Turkish words: [[design-template-zoo-design]].

## Steps (claim as `[plan:design-template-zoo] <n> — <topic>`; each waits for the one before it, Working mode 1)
- 1 (done, #8090): the `zoo-` palette block family `assets/js/style_designer_blocks_zoo.js` (today's schedule, animal spotlight, park map, conservation projects), `tests/designer_blocks_zoo_test.php`, `designer/designer-blocks-zoo.md`.
- 2 (done, #8143): `parts.php`: node builders, data, pick lists, `$money`, `$query`, `$event_on`, page shell, helpers; test.
- 3: `cards.php`: shared parts (account bar, notice, header, footer, staff bar), record and data cards, form fields, comment thread, newsletter and blog parts; test.
- 4: `pages_public.php`: home (`home_animals`, `home_events`, `home_posts` placed as widgets), `today`, `map`, `tickets`, `memberships`, `adopt`, `about`, `faq`; places the `zoo-` blocks; test.
- 5: `animals.php`: the Animals collection (`animals` by `?habitat=` and `?class=`, `animal`, `animal_new`, `home_animals`); test.
- 6: `mail.php` and `visits.php`: school and group visit requests (`visit_request` with `?visit=`, `?group=`, `?age=`, `visit_view`, `my_visits`, `staff_visits`, `privacy`, `email_reply`, `email_visit_new`, `email_visit_received`); test.
- 7: `events.php`: the Events collection by date (`events` from today on, `event`, `event_new`, `home_events`); test.
- 8: `shop.php`: the catalog (three groups, SKUs `ZOO-TICKET-`, `ZOO-MEMBER-`, `ZOO-ADOPT-<key>`), `buy_tickets`, `adopt_shop`, product, cart, checkout, order, `email_order`; test.
- 9: `standard.php`: contact, inbox, conversation, newsletter, News (`blog`, conservation news), blog post, help search, error ([[design-templates-standard-pages]]); test.
- 10: `account.php`: sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`, the account e-mail pages; test.
- 11: assembly: `pinegrap-zoo.php` (order, search sections, look, fonts, `pzo-` rules, folders), assembly test, the shared test lists, `designer/design-template-zoo.md`; the PR body names the `[NEW]` line; this plan and its design file are deleted.

## Where to start?
- The lowest step not marked done. Layout models: `pinegrap-skiresort/`, `pinegrap-museum/`; `pinegrap-restaurant/` for dated events. Lists: `?habitat=` `$habitat_titles`, `?class=` `$class_titles`, `?visit=` `$visit_choices`, `?group=` `$group_kinds`, `?age=` `$age_titles`. Never `?view=` ([[design-template-hotel]]).
- Steps 4-5 place the `zoo-` family blocks ([[designer-blocks-zoo]]); `$pin` needs the family rule `pg-zoo-pin`.

## Open questions
- None: all reuses shipped features; new behaviour goes to the architect (Working mode 10).
