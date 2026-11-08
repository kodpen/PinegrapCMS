---
name: design-template-drivingschool
description: Driving School template (pinegrap-drivingschool): licence classes, courses and pass stories as collections, enrolment and lesson requests, online lessons, no shop, pdr- look, tests.
---

## What does it ship?
- Home (`home_courses`, `home_stories` with `google_reviews` under Pass Stories, `home_posts`), `licences` and `licence_<key>` per class, `theory`, `practical_test`, `instructors`, `about`, `faq`; `courses` (`?licence=`, `?format=`), `course`, `course_new`; `enrol` (`?course=`), `enrolment_view`, `my_enrolments`, `staff_enrolments`, `privacy`, `email_reply`; `lesson_request` (`?licence=`, `?instructor=`), `lesson_request_view`, `my_lesson_requests`, `staff_lesson_requests`, `book_online`; `stories` (`?licence=`), `story`, `story_new`; the standard pages with the News and Learner Help, account and staff pages. The full list is `_test_ds_page_keys()`.
- No shop and no catalog: only `address_book` needs the shop; the school takes the payment at its office.
- Shared: account_bar, notice, header, footer, staff_bar, enrol_band, email_header/footer. Order 111, icon `bi-car-front`, look `bold`, palette `amber` (e-mail `#b45309`). Contact group `newsletter`. Built in #7847 to #8049 and the assembly.

## Why a main file plus a folder?
- As Hotel ([[design-template-hotel]]): `pinegrap-drivingschool.php` includes `parts.php`, `mail.php`, then `pages_public`, `courses`, `enrolments`, `lessons`, `stories`, `standard`, `account` in one scope, returns kept as `$…_part` (`$licences`, `$courses`, `$instructors`, `$stories` are parts.php's data).
- `courses.php` defines the list pieces (`$ds_filter`, `$ds_page_link`, `$ds_featured_choices`, `$ds_filter_bar`) that `stories.php` reuses; `enrolments.php` the request pieces (`$ds_ask_page`, `$ds_record_tree`, `$ds_record_list`) that `lessons.php` reuses, plus `privacy` and `email_reply`.

## How does it work without new product behaviour?
- Licences, instructors, branches, theory topics and test-day steps are fixed data drawn as cards.
- Courses: a collection ([[collection-fields]]) with licence and format pick lists, the price as words from `$money`, lessons and hours as words, office field `featured`; the course page opens `enrol?course=`. Pass stories: a collection by licence, office `featured` for the home.
- An enrolment and a lesson request are requests, never a place or a payment: consent linking `privacy`, office-only status, messages on the record page's Comments widget, `submitter_only` view; the lesson day has `min_date` today.
- `book_online` places the booking widget (`'tree' => 'starter'`) beside the lesson request ([[booking-templates]]).
- Not built: a theory quiz, online payment, progress tracking.

## What is the pdr- look?
- `pdr-brand`, `pdr-display`, `pdr-eyebrow`, `pdr-hero`, `pdr-lift`, `pdr-stat` (tabular figures), `pdr-plate` (a licence letter as a number plate), `pdr-badge`, `pdr-quote`; Barlow headings, Inter body. Field ids `drv-`. No fixed date in the sample; no `opacity-*` on text.
- Turkish: "sürücü kursu", "ehliyet sınıfı", "ders", "eğitmen", "direksiyon sınavı", "kayıt talebi", "başarı hikâyesi", "Haberler".
- Since 1.1.0 home and sector pages stand on its family: [[design-template-drivingschool-pages]].

## Which tests cover it?
- `design_template_drivingschool_test.php` (data, shared parts, `_test_ds_page_keys()`, `_test_ds_nodes()`) and `…_public_`, `…_courses_`, `…_enrolments_`, `…_lessons_`, `…_stories_`, `…_standard_`, `…_account_test.php`; `…_assembly_test.php`: pages and widgets, placeholders, `requires`, search sections, `pdr-` rules, four comment threads, samples, Google reviews.
- In the shared lists of the collection-form (`blog_new`, `course_new`, `staff_entry`, `story_new`), account-email, submitter-name, consent-privacy (`drv-en-consent`, `drv-ls-consent`), faded-text, booking-templates (`lesson_request`, `book_online`) and custom-form-rules (`drv-ls-date`) tests; the url-params test covers every template on its own.
