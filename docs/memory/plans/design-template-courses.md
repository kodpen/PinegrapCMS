---
name: design-template-courses
description: Plan for the Online Courses template (pinegrap-courses): courses as shop products granting a private course folder, lessons as pages in it, an all-access membership; parts in a folder; steps 1-7
---

## What is being built?
- An online school: home, courses, one overview per course (syllabus, instructor, price, Enrol), instructors, pricing, about, FAQ; each course's classroom and lessons; assignments; the standard pages and e-mails ([[design-templates-standard-pages]]). Id `pinegrap-courses`, 1.0.0, Bootstrap 5.3.8.

## How does access work without new product behaviour?
- Each sample course (three) has its own `private` folder holding its classroom and lesson pages. The course is a product of a Courses catalog group (`shippable => false`) with the existing `grant_private_access` + `private_folder`: the paid order gives the buyer the folder (`pg_order_complete_line_folder_access()`).
- All access: a Membership product (`membership_renewal` days) and a `membership` folder (members' library: workshops, recordings). Folder access `membership` already exists on the site.
- Without the shop the course pages still stand: Enrol opens an enrolment request (Enrolments form record, course from `_cf.url_param`); staff give the folder in the panel.

## Why lessons as pages and not form records?
- A record's view page reads any record of its form, so one course's student could open another's lessons through it; a page is guarded by its folder. Each course's first lesson is a free preview in the public folder.
- Lesson questions: the page's `comments` array on the Comments widget. Assignments: an Assignments form (course, file, notes), `submitter_only` view with staff feedback on the Comments widget, office-use status (Submitted, Needs revision, Passed), My Assignments, staff list.
- Not built (new behaviour): progress, scored quizzes, certificates, drip release, a list of only the bought courses (My Courses lists all; the folder decides).

## Why parts in a folder?
- As Events: steps 1-6 write `includes/design_templates/pinegrap-courses/` (not globbed); step 7's `pinegrap-courses.php` offers it. Classes `pco-`.

## Which steps are there?
- 1-4 done: engine and `parts.php` (#4770), `pages_public.php` (#4788), `learn.php` (#4838), `assignments.php` and `mail.php` (#4849).
- 5a Shop: `shop.php` (catalog: Courses group, a group per course `courses_<key>` and `membership`, one product each; buy boxes, product, cart, checkout, order, `email_order`). **Done (#4912).**
- 5b Standard: `standard.php`, test `…_standard_test.php`. **Done (#4880).**
- 6 Account and staff: `account.php`; test `…_account_test.php`.
- 7 Assembly: `pinegrap-courses.php` (header, order, icon `bi-mortarboard`, highlights, look, palette, contact group, folders, catalog, `pco-` User Styles, search map), shared tests' lists, `designer/design-template-courses.md`, plan deleted; PR body names `[NEW] Added the Online Courses design template`.

## Where to start?
- Step 6, then 7. Already named: pages `enrolment`, `enrolment_view`, `assignment`, `assignment_view`, `my_assignments`, `staff_enrolments`, `staff_assignments`, `email_reply` and four form e-mails (`assignments.php`, also widget `email_comment`); `standard.php` has contact, inbox, conversation, newsletter, blog, blog_post, search, privacy, error and their e-mails, widgets `home_posts`, `newsletter_band`; lists read `{{tab:blog_new}}` (6); `shop.php` has `buy_<course>`, `buy_membership`, `cart_link` and the shop pages; widget `login_region` (6); folders `public`, `registration`, `private`, `course_<key>` (private), `members` (membership). `tests/design_template_courses_test.php` lists the page and widget keys.
- `mail.php`: the `$mail_*` builders, `$email_header_tree`, `$email_footer_tree`; inline colours are teal (`#0f766e`), so step 7 should suggest a teal palette (`ocean`) or change them.
- Copy from `pinegrap-events/` (`account.php`; step 7 from `pinegrap-events.php`, taking `catalog` from `shop.php`).
