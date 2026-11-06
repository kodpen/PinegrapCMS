---
name: design-template-recruitment
description: Plan: the Recruitment Agency design template (pinegrap-recruitment): jobs by sector and city, applications, employer enquiries, career advice; ~1,000-line steps
---

## What is it?
- A design template `pinegrap-recruitment` for a recruitment agency: home, sectors with a page per sector (`sector_<key>`), cities, for candidates (how it works, register a CV), for employers (services, how it works), job listings filtered by sector, city and contract type with a page per job, applications followed from the account, employer enquiries (a request for staff) followed from the account, the consultants, about, FAQ, Career Advice (the blog: the candidate guides), job alerts (the newsletter), contact, sign-in and a staff area. No shop: an agency sells nothing online. Started by a coordinator (`workflow/coordinator-no-plans.md`).
- Built like Construction, Gym and Law: `pinegrap-recruitment.php` plus the folder `pinegrap-recruitment/` (`parts.php`, `mail.php`, `pages_public.php`, `jobs.php`, `applications.php`, `employers.php`, `standard.php`, `account.php`), each page file returns `pages` and `widgets`, kept as `$…_part` in the main file ([[design-template-hotel]]).

## Design
- What it reuses, what it does not build, the look and the Turkish words: [[design-template-recruitment-design]].

## Steps (claim as `[plan:design-template-recruitment] <n> — <topic>`; each waits for the one before it, Working mode 1)
- 1: `parts.php` (node builders, the agency's data: sectors, cities, contract types, work modes, sample jobs with salary words, duties and requirements, candidate and employer steps, employer services, consultants, figures, testimonials, pick lists; shared parts: account bar, notice, header, footer, two-way call band, staff bar; job, sector, city, service, consultant and testimonial cards, job facts, form fields, comment thread, blog parts); `tests/design_template_recruitment_test.php`.
- 2: `pages_public.php`: home (`home_jobs`, `home_posts`), `sectors` and one `sector_<key>` per sector, `cities`, `candidates`, `employers`, `about`, `team`, `faq`; test.
- 3: `jobs.php`: the Jobs collection (`jobs` by `?sector=`, `?city=` and `?type=`, the `job` page with facts, duties, requirements, the consultant and an apply link `?job=`, `job_new`), `home_jobs`; test.
- 4: `mail.php` (the e-mail builders) and `applications.php`: applications (`apply` with `?job=`, open application without one, `application_view`, `my_applications`, `staff_applications`, `privacy`, `email_reply`); test.
- 5: `employers.php`: employer enquiries (`hire` with `?sector=`, `enquiry_view`, `my_enquiries`, `staff_enquiries`); test.
- 6: `standard.php`: contact, inbox, conversation, job alerts (`newsletter`), Career Advice (the blog), blog post, help search, error ([[design-templates-standard-pages]]); test.
- 7: `account.php`: sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`, the e-mail pages; test.
- 8: assembly: `pinegrap-recruitment.php` (order, search sections, look, fonts, `prc-` rules, folders, contact group, shared parts), `…_assembly_test.php`, the template in the shared test lists (collection-form, account-email, submitter-name, consent-privacy, faded-text), `designer/design-template-recruitment.md`; the PR body names the `[NEW]` line; this plan and its design file are deleted.

## Where to start?
- The next free step is the lowest one not marked done. Models: `pinegrap-construction/` (careers, quote requests), `pinegrap-law/` (applications), `pinegrap-travel/` (a collection with filters). The application reads `?job=` (`$application_job_choices`), the employer enquiry `?sector=` (`$enquiry_sector_choices`); the job list filters `?sector=`, `?city=` and `?type=`. Sector pages are `sector_<key>` under `$sector_slugs`. `_test_rc_page_keys()` is shared by test files sorting after `…_test.php`.

## Open questions
- None: everything above reuses shipped features. A step that finds it needs new behaviour asks the architect (Working mode 10).
