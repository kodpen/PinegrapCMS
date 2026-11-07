---
name: design-template-construction
description: Plan: the Construction Firm design template (pinegrap-construction): projects portfolio, services, quote requests, team, careers, news; ~1,000-line steps
---

## What is it?
- A design template `pinegrap-construction` for a building contractor: home, services (residential, commercial, renovation, civil works, design and build, project management), a projects portfolio with a page per project, sectors, our process, safety and quality, the team, about, FAQ, quote requests followed from the account, careers with job openings and applications, News (the blog), contact, sign-in and a staff area. No shop: a contractor sells nothing online. Started by a coordinator after the owner's list (`workflow/coordinator-no-plans.md`).
- Built like Gym, Travel and Law: `pinegrap-construction.php` plus the folder `pinegrap-construction/` (`parts.php`, `mail.php`, `pages_public.php`, `projects.php`, `quotes.php`, `careers.php`, `standard.php`, `account.php`), each page file returns `pages` and `widgets`, kept as `$…_part` in the main file ([[design-template-hotel]]).

## Design
- What it reuses, what it does not build, the look and the Turkish words: [[design-template-construction-design]].

## Steps (claim as `[plan:design-template-construction] <n> — <topic>`; each waits for the one before it, Working mode 1)
- 1 (done, #7543): `parts.php` (node builders, the firm's data: services, sectors, sample projects with facts, process steps, team, figures, accreditations, testimonials, job openings, pick lists; shared parts: account bar, notice, header, footer, quote band, staff bar; service, project, team and testimonial cards, project facts, form fields, comment thread, blog parts); `tests/design_template_construction_test.php`.

- 2 (done, #7575): `pages_public.php`: home (`home_projects`, `home_posts`), `services` and one `service_<key>` per service, `sectors`, `process`, `safety`, `about`, `team`, `faq`; `tests/design_template_construction_public_test.php`.

- 3 (done, #7614): `projects.php`: the Projects collection (`projects` by `?sector=` and `?service=`, the `project` page with facts, photos, scope and the client's words, `project_new`), `home_projects`; test.

- 4 (done, #7633): `mail.php` (the e-mail builders) and `quotes.php`: quote requests (`quote` with `?service=`, `quote_view`, `my_quotes`, `staff_quotes`, `privacy`, `email_reply`) and the site visit request; test.

- 5 (done, #7679): `careers.php`: the Jobs collection (`careers`, `job` with an apply link `?job=`, `job_new`) and applications (`application`, `application_view`, `my_applications`, `staff_applications`); test.

- 6 (done, #7710): `standard.php`: contact, inbox, conversation, newsletter, News (the blog), blog post, help search, error ([[design-templates-standard-pages]]), `home_posts`, `google_reviews`; no newsletter band (the quote band sits above the footer); the reply e-mail stays quotes.php's; test.

- 7: `account.php`: sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`, the e-mail pages; test.

- 8: assembly: `pinegrap-construction.php` (order, search sections, look, fonts, `pcn-` rules, folders, contact group, shared parts), `…_assembly_test.php`, the template in the shared test lists (collection-form, account-email, submitter-name, consent-privacy, faded-text), `designer/design-template-construction.md`; the PR body names the `[NEW]` line; this plan and its design file are deleted.

## Where to start?
- The next free step is the lowest one not marked done. Models: `pinegrap-gym/`, `pinegrap-travel/`, `pinegrap-law/` (careers). The quote request reads `?service=` (`$quote_service_choices`), the application `?job=` (`$application_job_choices`); the project list filters `?sector=` and `?service=`. Service pages are `service_<key>` under `$service_slugs`. `_test_cn_page_keys()` is shared by test files sorting after `…_test.php`.

## Open questions
- None: everything above reuses shipped features. A step that finds it needs new behaviour asks the architect (Working mode 10).
