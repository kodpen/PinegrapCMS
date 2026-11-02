---
name: design-template-courses
description: Online Courses template (pinegrap-courses): courses as products granting a private folder, lessons as pages, membership library, enrolment and assignments, what is not built, pco- look, tests.
---

## What does it ship?
- Home, courses, `course_<key>` (syllabus, instructor, price, Enrol), instructors, pricing, about, FAQ; `learn_<key>` and `lesson_<key>_<n>` per course, `my_courses`, `library`; `enrolment`, `enrolment_view`, `assignment`, `assignment_view`, `my_assignments`, `staff_enrolments`, `staff_assignments`; product, cart, checkout, order; the standard pages ([[design-templates-standard-pages]]); sign-in and My Account pages, `staff_directory`, `staff`, `blog_new`, `staff_entry`; the e-mail pages.
- Shared: account_bar, notice, header, footer, staff_bar, enrol_band, email_header/footer. Order 67, icon `bi-mortarboard`, look `modern-soft`, palette `ocean` (the e-mails' inline colours are its teal `#0f766e`). Contact group `newsletter` (Courses Newsletter). Built in #4770, #4788, #4838, #4849, #4880, #4912, #4930 and the assembly PR.

## Why a main file plus a folder?
- As Events ([[design-template-events]]): `pinegrap-courses.php` includes `parts.php`, `mail.php`, then `pages_public.php`, `learn.php`, `assignments.php`, `shop.php`, `standard.php`, `account.php` in one scope. Their return values are kept as `$…_part`: a part sets its own variables in that scope (`account.php` sets `$shop`), and a plain `$shop` was overwritten.

## How does access work without new product behaviour?
- Each course (three samples) has its own `private` folder (`course_<key>`) holding its classroom and lessons. Its product, in a Courses catalog group (`courses_<key>`, `shippable => false`), carries `private_folder` + `private_days => 0`: the paid order grants the folder (`pg_order_complete_line_folder_access()`).
- All access: a Membership product (`membership_days`) renews the buyer's membership; the members' library is in the `members` folder (access `membership`).
- Without the shop the course pages still stand: Enrol opens an enrolment request (course from `_cf.url_param`); the staff give the folder in the panel. Only the shop pages carry `requires => ecommerce`.

## Why lessons as pages and not form records?
- A record's view page reads any record of its form, so one course's student could open another's lessons; a page is guarded by its folder. Each course's first lesson is a free preview in `public`.
- Lesson questions: one Comments widget per lesson (`questions_<lesson>`). Assignments: a form (course, file, notes) with `submitter_only` view, staff feedback in the Comments widget, an office-use status (Submitted, Needs revision, Passed), My Assignments and the staff list.

## What is deliberately not built?
- Progress, scored quizzes, certificates, drip release and a list of only the bought courses (My Courses lists all; the folder decides which classroom opens): new behaviour.

## What is the pco- look?
- `pco-brand` (header name), `pco-eyebrow` (section header line), `pco-hero` (home's first section), `pco-lift` (course and instructor cards); two fonts (Nunito headings, Inter body) in `assets`. Form field ids share the prefix (`pco-enrol-…`), so the class check reads `cssClass` only.
- No `opacity-*` on text (the enrol band and the lesson length had it; [[design-template-text-contrast]]).

## Which tests cover it?
- `design_template_courses_test.php` (data, public, learning, enrolment and assignments, shop), `…_standard_test.php`, `…_account_test.php` load the parts; `…_assembly_test.php` the main file: offered, folders and their access, every course product grants a template folder, pages in order, widgets once and all placed, placeholders name pages and folders, contact search sections, `pco-` classes equal rules. It is in the shared lists of the collection-form, account-e-mail, submitter-name, consent-privacy and faded-text tests.
