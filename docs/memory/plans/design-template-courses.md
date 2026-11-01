---
name: design-template-courses
description: Plan for the Online Courses template (pinegrap-courses): courses as shop products granting a private course folder, lessons as pages in it, an all-access membership; parts in a folder; steps 1-7
---

## What is being built?
- An online school: home, courses, one overview per course (syllabus, instructor, price, Enrol), instructors, pricing, about, FAQ; each course's classroom and lessons; assignments; the standard pages and e-mails ([[design-templates-standard-pages]]). Id `pinegrap-courses`, 1.0.0, Bootstrap 5.3.8, every word through `lang()`.

## How does access work without new product behaviour?
- Each sample course (three) has its own `private` folder holding its classroom and lesson pages. The course is a product of a Courses catalog group (`shippable => false`) with the existing `grant_private_access` + `private_folder`: the paid order gives the buyer the folder (`pg_order_complete_line_folder_access()`).
- All access: a Membership product (`membership_renewal` days) and a `membership` folder (members' library: workshops, recordings). Folder access `membership` already exists on the site.
- Without the shop the course pages still stand: Enrol opens an enrolment request (Enrolments form record, course from `_cf.url_param`); staff give the folder in the panel.

## Why lessons as pages and not form records?
- A record's view page reads any record of its form, so one course's student could open another's lessons through it; a page is guarded by its folder. A new lesson is a copied lesson page. Each course's first lesson is a free preview in the public folder.
- Lesson questions: the page's `comments` array on the Comments widget. Assignments: an Assignments form (course, file, notes), `submitter_only` view with staff feedback on the Comments widget, office-use status (Submitted, Needs revision, Passed), My Assignments, staff list.
- Not built (new behaviour): progress, scored quizzes, certificates, drip release, a list of only the bought courses (My Courses lists all; the folder decides).

## Template engine additions (step 1)
- `_pg_tpl_folders()` accepts `'access' => 'membership'`; `pg_design_template_prepare()` makes the folders before the catalog; a catalog product may say `'private_folder' => '<folder key>'`, `'private_days'`, `'membership_days'`, written to the existing product columns; a test; installed sites unchanged.

## Why parts in a folder?
- As Events: steps 1-6 write `includes/design_templates/pinegrap-courses/` (not globbed); step 7's `pinegrap-courses.php` offers it. Classes `pco-`.

## Which steps are there?
- 1 Engine and data: the engine additions above with tests; `parts.php` (builders, courses, instructors, lessons data, shared header/footer/notice); test `design_template_courses_test.php`.
- 2 Public pages: `pages_public.php` (home with `google_reviews`, courses, three course overviews, instructors, pricing, about, FAQ).
- 3 Learning: `learn.php` (classroom and lesson pages per course folder, free previews, lesson questions, members' library).
- 4 Enrolment and assignments: `assignments.php`, `mail.php` (enrolment request, assignment, views, My Assignments, staff lists, notify e-mails).
- 5a Shop: `shop.php` (Courses group, course and membership products, listing, product, cart, checkout, order, `email_order`; `requires ecommerce`).
- 5b Standard: `standard.php` (contact, inbox, conversation, newsletter, blog, blog post, search, error, privacy); test `…_standard_test.php`.
- 6 Account and staff: `account.php`; test `…_account_test.php`.
- 7 Assembly: `pinegrap-courses.php` (header, order, icon `bi-mortarboard`, highlights, look, palette, contact group, folders, catalog, `pco-` User Styles, search map), shared tests' lists, `designer/design-template-courses.md`, plan deleted; PR body names `[NEW] Added the Online Courses design template`.

## Where to start?
- `pinegrap-events/` (same layout), `fn/designer.php` (`_pg_tpl_catalog()`, `_pg_tpl_folders()`), `pg_pb_create_product()`, [[design-templates-catalog-and-look]].
