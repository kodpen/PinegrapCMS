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
- 1 (done, #7639): `parts.php` (node builders, the studio's data and pick lists, shared parts, cards, project facts, credits, gallery, form fields, comment thread, blog parts); `tests/design_template_architecture_test.php`.

- 2 (claimed): `pages_public.php`: home (`home_projects`, `home_posts`), `services` and one `service_<key>` per service, `studio`, `team`, `process`, `awards` (awards and press), `faq`; `tests/design_template_architecture_public_test.php`.

- 3: `projects.php`: the Projects collection (`projects` by `?type=` and `?location=`, the `project` page with facts, the gallery, the brief, the credits and the client's words, `project_new`), `home_projects`; test.

- 4: `mail.php` (the e-mail builders) and `enquiries.php`: project enquiries (`enquiry` with `?service=`, `enquiry_view`, `my_enquiries`, `staff_enquiries`), the consultation request (`consultation`, `consultation_view`, `my_consultations`, `staff_consultations`), `privacy`, `email_reply`; test.

- 5: `careers.php`: the Jobs collection (`careers`, `job` with an apply link `?job=`, `job_new`) and applications (`application`, `application_view`, `my_applications`, `staff_applications`); test.

- 6: `standard.php`: contact, inbox, conversation, newsletter, the Journal (`blog`), blog post, help search, error ([[design-templates-standard-pages]]); test.

- 7: `account.php`: sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`, the e-mail pages; test.

- 8: assembly: `pinegrap-architecture.php` (order, search sections, look, fonts, `pai-` rules, folders, contact group, shared parts), `…_assembly_test.php`, the template in the shared test lists (collection-form, account-email, submitter-name, consent-privacy, faded-text), `designer/design-template-architecture.md`; the PR body names the `[NEW]` line; this plan and its design file are deleted.

## Where to start?
- The next free step is the lowest one not marked done. Models: `pinegrap-construction/`, `pinegrap-school/`. The enquiry reads `?service=` (`$enquiry_service_choices`), the consultation `?service=` too, the application `?job=` (`$application_job_choices`); the project list filters `?type=` and `?location=` in the words of `$type_titles` and `$location_titles`. Service pages are `service_<key>` under `$services`. `_test_ai_page_keys()` in the step 1 test lists every page key of the finished template and is shared by test files sorting after it.

## Open questions
- None: everything above reuses shipped features. A step that finds it needs new behaviour asks the architect (Working mode 10).
