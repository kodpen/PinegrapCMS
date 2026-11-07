---
name: design-template-architecture
description: Plan: the Architecture & Interiors Studio design template (pinegrap-architecture): projects by type and place, studio, services, awards, journal, enquiries, careers
---

## What is it?
- A design template `pinegrap-architecture` for an architecture and interior design studio: home, services (architecture, interior design, adaptive reuse, masterplanning, landscape, furniture and objects), a projects portfolio by type and location with a page per project (gallery and credits), the studio, the team, how we work, awards and press, FAQ, project enquiries and a consultation request followed from the account, careers with job openings and applications, the Journal (the blog), contact, sign-in and a staff area. No shop: a studio sells its time, quoted per project.
- A design studio, not a builder: it designs and supervises, and credits the builders and engineers it worked with.
- Built like Construction, Gym and Law: `pinegrap-architecture.php` plus the folder `pinegrap-architecture/` (`parts.php`, `mail.php`, `pages_public.php`, `projects.php`, `enquiries.php`, `careers.php`, `standard.php`, `account.php`), each page file returns `pages` and `widgets` ([[design-template-hotel]]).

## Design
- What it reuses, what it does not build, the look and the Turkish words: [[design-template-architecture-design]].

## Steps (claim as `[plan:design-template-architecture] <n> — <topic>`; each waits for the one before it, Working mode 1)
- 1 (done, #7639): `parts.php` (builders, data, pick lists, shared parts, cards, form fields, comment thread, blog parts); test.
- 2 (done, #7767): `pages_public.php` (home, services and `service_<key>`, studio, team, process, awards, faq); test.

- 3 (done, #7777): `projects.php` (`projects` by `?type=`/`?location=`, `project`, `project_new`, `home_projects`); test.

- 4 (done, #7790): `mail.php`, `enquiries.php` (enquiry and consultation pages, `privacy`, `email_reply`, their e-mail pages); test.

- 5 (done, #7802): `careers.php` (Job Openings collection, applications with portfolio and CV, their pages and e-mails); test.

- 6 (done, #7814): `standard.php` (contact, inbox, conversation, newsletter, Journal, post, search, error, their e-mails, `home_posts`, `google_reviews`); test.

- 7 (done, #7828): `account.php`: sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`, the e-mail pages; test.

- 8: assembly: `pinegrap-architecture.php` (order, search sections, look, fonts, `pai-` rules, folders, contact group, shared parts), `…_assembly_test.php`, the template in the shared test lists (collection-form, account-email, submitter-name, consent-privacy, faded-text), `designer/design-template-architecture.md`; the PR body names the `[NEW]` line; this plan and its design file are deleted.

## Where to start?
- The next free step is the lowest one not marked done. Step 8 follows `pinegrap-construction.php` and `tests/design_template_construction_assembly_test.php`; `tests/design_template_architecture_account_test.php` already checks that the parts carry every key of `_test_ai_page_keys()`. Models: `pinegrap-construction/`, `pinegrap-school/`. The enquiry reads `?service=` (`$enquiry_service_choices`), the consultation `?service=` too, the application `?job=` (`$application_job_choices`); the project list filters `?type=` and `?location=` in the words of `$type_titles` and `$location_titles`. Service pages are `service_<key>` under `$services`. `_test_ai_page_keys()` in the step 1 test lists every page key of the finished template.

## Open questions
- None: everything above reuses shipped features. A step that finds it needs new behaviour asks the architect (Working mode 10).
