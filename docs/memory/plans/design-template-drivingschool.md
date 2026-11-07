---
name: design-template-drivingschool
description: Plan: the Driving School design template (pinegrap-drivingschool): licences, priced courses, instructors, lesson requests, pass stories; ~1,000-line steps
---

## What is it?
- A design template `pinegrap-drivingschool` for a driving school: home, the licence classes (car, automatic car, motorcycle, light truck, car with trailer) with a page each, the courses with their prices (standard, intensive, refresher, automatic only, theory only) as a collection, the instructors, the theory test (how it works, topics, tips), the practical test (what the examiner checks, test day), lesson requests and online booking of a free time, course enrolment requests followed from the account, pass stories, about, FAQ, News (the blog), contact, privacy, sign-in and a staff area. No shop: the school takes the payment at its desk after the enrolment is confirmed.
- Built like Gym and Veterinary: `pinegrap-drivingschool.php` plus the folder `pinegrap-drivingschool/` (`parts.php`, `mail.php`, `pages_public.php`, `courses.php`, `enrolments.php`, `lessons.php`, `stories.php`, `standard.php`, `account.php`), each page file returns `pages` and `widgets`, kept as `$…_part` in the main file ([[design-template-hotel]]).

## Design
- What it reuses, what it does not build, the look and the Turkish words: [[design-template-drivingschool-design]].

## Steps (claim as `[plan:design-template-drivingschool] <n> — <topic>`; each waits for the one before it, Working mode 1)
- 1 (done, #7847): `parts.php`; test.

- 2 (done, #7910): `pages_public.php` (home, licences, `licence_<key>`, theory, practical test, instructors, about, faq); test.

- 3 (done, #7923): `courses.php` (`courses` by `?licence=` and `?format=`, `course`, `course_new`, `home_courses`); test.

- 4: `mail.php` (the e-mail builders) and `enrolments.php`: course enrolment requests (`enrol` with `?course=`, `enrolment_view`, `my_enrolments`, `staff_enrolments`), `privacy`, `email_reply`; test.

- 5: `lessons.php`: lesson requests (`lesson_request` with `?licence=` and `?instructor=`, `lesson_request_view`, `my_lesson_requests`, `staff_lesson_requests`) and `book_online`; test.

- 6: `stories.php`: pass stories as a collection (`stories` by `?licence=`, `story`, `story_new`, `home_stories`); test.

- 7: `standard.php`: contact, inbox, conversation, newsletter, News (`blog`), blog post, help search, error ([[design-templates-standard-pages]]); test.

- 8: `account.php`: sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`, the e-mail pages; test.

- 9: assembly: `pinegrap-drivingschool.php` (order, search sections, look, fonts, `pdr-` rules, folders, contact group, shared parts), `…_assembly_test.php`, the template in the shared test lists (collection-form, account-email, submitter-name, consent-privacy, faded-text, url-params, booking-templates), `designer/design-template-drivingschool.md`; the PR body names the `[NEW]` line; this plan and its design file are deleted.

## Where to start?
- The next free step is the lowest one not marked done. Models: `pinegrap-gym/`, `pinegrap-veterinary/` (`book_online`), `pinegrap-filmstudio/parts.php` (`$money`). The lists filter `?licence=` (`$licence_titles`) and `?format=` (`$format_titles`); the enrolment reads `?course=` (`$enrolment_course_choices`), the lesson request `?licence=` and `?instructor=` (`$instructor_choices`). Licence pages are `licence_<key>` under `$licences`. `_test_ds_page_keys()`, `_test_ds_nodes()` and `_test_ds_filter_links()` (step 1 test) serve the later tests through `require_once`. `courses.php` defines `$ds_filter`, `$ds_page_link`, `$ds_featured_choices` and `$ds_filter_bar($page, $groups)` for the later lists (`stories` by `?licence=`), so the main file and those tests include it first; the course page sends `enrol?course=` with the record's name.

## Open questions
- None: everything above reuses shipped features. A step that finds it needs new behaviour asks the architect (Working mode 10).
