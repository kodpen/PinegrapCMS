---
name: design-template-filmstudio
description: Plan: the Film Studio design template (pinegrap-filmstudio): showreel, projects by industry and service, services, the studio's stages for hire, crew, project enquiries, crew calls
---

## What is it?
- A design template `pinegrap-filmstudio` for a film and video production studio: home with the showreel, services (commercials, brand films, music videos, documentaries, corporate and event films, post-production) with a page each, the work as a projects collection by industry and service with a page per project (the film's link, stills, credits, the client's words), how a production runs, the studio (stages, edit and grading suites) with a hire request, the crew, about, FAQ, project enquiries followed from the account, crew calls with applications, the Journal (the blog), contact, sign-in and a staff area. No shop: a studio quotes per production.
- Built like Construction: `pinegrap-filmstudio.php` plus the folder `pinegrap-filmstudio/`, one file per step below; each page file returns `pages` and `widgets` ([[design-template-hotel]]).

## Design
- What it reuses, what it does not build, the look and the Turkish words: [[design-template-filmstudio-design]].

## Steps (claim as `[plan:design-template-filmstudio] <n> — <topic>`; each waits for the one before it, Working mode 1)
- 1 (done, #7652): `parts.php` (node builders, the studio's data and pick lists, shared parts: account bar, notice, header, footer, enquiry band, staff bar; the showreel player, project, service, crew, facility, testimonial and job cards, project facts, credits, form fields, comment thread, blog parts); `tests/design_template_filmstudio_test.php`.

- 2 (done, #7808): `pages_public.php`: home (showreel, `home_projects`, `home_posts`), `services` and one `service_<key>` per service, `process`, `studio`, `crew`, `about`, `faq`; `tests/design_template_filmstudio_public_test.php`.

- 3 (done, #7879): `projects.php`: the Projects collection (`projects` by `?industry=` and `?service=`, the `project` page with the film's link, stills, facts, the credits and the client's words, `project_new`), `home_projects`; test.

- 4 (done, #7936): `mail.php` (e-mail builders), `enquiries.php`: project enquiries (`enquiry` with `?service=`, `enquiry_view`, `my_enquiries`, `staff_enquiries`), studio hire requests (`studio_hire` with `?space=`, `studio_hire_view`, `my_studio_hires`, `staff_studio_hires`), `privacy`, `email_reply`; test.

- 5 (done, #8088): `careers.php`: crew calls (Jobs collection) and applications; test.

- 6 (done, #8108): `standard.php`: the standard pages, the Journal as the blog; test.

- 7: `account.php`: sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`, the e-mail pages; test.

- 8: assembly: `pinegrap-filmstudio.php` (order, search sections, look, fonts, `pfm-` rules, folders, contact group, shared parts), `…_assembly_test.php`, the template in the shared test lists (collection-form, account-email, submitter-name, consent-privacy, faded-text), `designer/design-template-filmstudio.md`; the PR body names the `[NEW]` line; this plan and its design file are deleted.

## Where to start?
- The next free step is the lowest one not marked done. Models: `pinegrap-construction/`, `pinegrap-courses/learn.php` (the video player). The enquiry reads `?service=` (`$enquiry_service_choices`), the studio hire `?space=` (`$space_titles`), the application `?job=` (`$application_job_choices`); the project list filters `?industry=` and `?service=` in the words of `$industry_titles` and `$service_titles`. Service pages are `service_<key>` under `$services`. `_test_fm_page_keys()` (step 1 test) lists every page key of the finished template.

## Open questions
- None: everything above reuses shipped features. A step that finds it needs new behaviour asks the architect (Working mode 10).
