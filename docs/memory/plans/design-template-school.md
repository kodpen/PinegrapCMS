---
name: design-template-school
description: Plan: the Private School / Kindergarten design template (pinegrap-school): programmes and classes, staff, admission applications, events, news, parent contact; ~1,000-line steps
---

## What is it?
- A design template `pinegrap-school` for a private school or kindergarten: home, programmes (nursery, kindergarten, primary, after-school clubs, summer school), a page per programme, classes and a day at school, our approach, campus and facilities, the staff, about, fees as words, FAQ, admission applications followed from the parent's account, a visit (school tour) request, school events with a page per event, News (the blog), parent contact, privacy, sign-in and a staff area. No shop: a school takes no payment online. Not `pinegrap-courses` (online courses sold to learners). Started by a coordinator from the free-work list (`workflow/coordinator-no-plans.md`).
- Built like Construction, Gym and Travel: `pinegrap-school.php` plus the folder `pinegrap-school/` (`parts.php`, `mail.php`, `pages_public.php`, `events.php`, `admissions.php`, `standard.php`, `account.php`), each page file returns `pages` and `widgets`, kept as `$…_part` in the main file ([[design-template-hotel]]).

## Design
- What it reuses, what it does not build, the look and the Turkish words: [[design-template-school-design]].

## Steps (claim as `[plan:design-template-school] <n> — <topic>`; each waits for the one before it, Working mode 1)
- 1 (done, #7582): `parts.php` (node builders, the school's data: programmes with age ranges, classes, daily routine, approach pillars, facilities, staff, figures, accreditations, parents' words, event samples, pick lists; shared parts: account bar, notice, header, footer, admissions band, staff bar; programme, staff, event and testimonial cards, form fields, comment thread, blog parts); `tests/design_template_school_test.php`.

- 2 (done, #7625): `pages_public.php`: home (`home_events`, `home_posts`), `programmes` and one `programme_<key>` per programme, `day`, `approach`, `campus`, `staff_team`, `about`, `fees`, `faq`; `tests/design_template_school_public_test.php`.

- 3 (done, #7645): `events.php`: the Events collection (`events` by `?programme=`, the `event` page with date as words, place and details, `event_new`), `home_events`; test.

- 4: `mail.php` (the e-mail builders) and `admissions.php`: admission applications (`apply` with `?programme=`, `application_view`, `my_applications`, `staff_applications`, `privacy`, `email_reply`) and the visit request (`visit`, `staff_visits`); test.

- 5: `standard.php`: contact, inbox, conversation, newsletter, News (the blog), blog post, help search, error ([[design-templates-standard-pages]]); test.

- 6: `account.php`: sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`, the e-mail pages; test.

- 7: assembly: `pinegrap-school.php` (order, search sections, look, fonts, `psh-` rules, folders, contact group, shared parts), `…_assembly_test.php`, the template in the shared test lists (collection-form, account-email, submitter-name, consent-privacy, faded-text), `designer/design-template-school.md`; the PR body names the `[NEW]` line; this plan and its design file are deleted.

## Where to start?
- The next free step is the lowest one not marked done. Model files: `pinegrap-construction/`, `pinegrap-gym/`, `pinegrap-travel/`. The application reads `?programme=` (programme pages) in `$application_programme_choices`; the event list filters `?programme=` in `$programme_titles`. Later tests share `_test_sh_page_keys()` from `tests/design_template_school_test.php`.

## Open questions
- None: everything above reuses shipped features. A step that finds it needs new behaviour asks the architect (Working mode 10).
