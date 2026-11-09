---
name: design-template-recruitment
description: Recruitment Agency template (pinegrap-recruitment): jobs as a collection, applications and staff requests followed from the account, Career Advice, job alerts, no shop, prc- look, tests.
---

## What does it ship?
- Home (places `home_jobs`, `google_reviews` under "What people say", `home_posts`), `sectors` and six `sector_<key>`, `cities`, `candidates`, `employers`, `about`, `team`, `faq`; `jobs` (`?sector=`, `?city=`, `?type=`), the `job` record page (apply link `?job=`), `job_new` (staff); `apply` (with `?job=`, or an open application, which is also how a CV is registered), `application_view`, `my_applications`, `staff_applications`, `privacy`, `email_reply`; `hire` (`?sector=`), `enquiry_view`, `my_enquiries`, `staff_enquiries`; contact, inbox, conversation, `newsletter` (job alerts), `blog` (Career Advice), `blog_post`, `search`, `error`; sign-in and account pages, staff pages; the e-mail pages.
- Shared: account_bar, notice, header, footer, staff_bar, call_band, email_header/footer. Order 137 (133 Gallery, 135 Ski Resort; the 99 first planned is Campsite's), icon `bi-person-workspace`, look `modern-soft`, palette `emerald`. Contact group `newsletter` (named "Job Alerts").
- Built in #7656, #7822, #7833, #7935, #8132, #8156, #8184 and the assembly (#8207).

## Why a main file plus a folder?
- `pinegrap-recruitment.php` includes `parts.php`, `pages_public.php`, `jobs.php`, `mail.php`, `applications.php`, `employers.php`, `standard.php`, `account.php` in one scope, each return kept as `$…_part`; `$sectors`, `$cities`, `$jobs`, `$consultants` are parts.php's data. `employers.php` builds with the record pieces of `applications.php` (`$rc_detail_widget`, `$rc_messages_widget`); `email_reply` and `privacy` are `applications.php`'s.
- Search sections are marked where built (`$searchable`); the office details on `contact` are `rc-office`. The main file adds none.

## Which shipped features does it reuse? (no new schema, module or behaviour)
- Jobs are a collection ([[collection-fields]]): sector, city, contract type and work mode as pick lists whose answers are the words the filters send, the salary as a band in words, the employer described, never named, office field `featured`.
- An application is a record of the Job Applications form (`?job=` through `_cf.url_param`), the CV in `private`, consent linking `privacy`, office-only status, messages on the record page's Comments widget, `submitter_only` view. A staff request the same way on the Staff Requests form (`?sector=`, the job description in `private`).
- Consultants are fixed data; Career Advice is the standard blog; job alerts are the newsletter. No shop and no catalog: only `address_book` carries `requires => ecommerce`.
- Not built: sector-matched alerts, a candidate database, employers posting jobs, timesheets, interview booking, a salary calculator.

## What is the prc- look?
- `prc-brand`, `prc-display`, `prc-eyebrow`, `prc-hero`, `prc-lift`, `prc-quote`, `prc-salary`, `prc-stat`; field ids `rc-`. Plus Jakarta Sans headings, Inter body. No `opacity-*` on text ([[design-template-text-contrast]]); no fixed date, year or month in the sample.
- Turkish: "iş ilanı", "başvuru", "aday", "işveren", "personel talebi", "danışman", "Kariyer Rehberi", "İş Alarmı"; the template's name "İşe Alım Ajansı".

## Which tests cover it?
- `design_template_recruitment_test.php` (data, shared parts, `_test_rc_page_keys()`) and `…_public_`, `…_jobs_`, `…_applications_`, `…_employers_`, `…_standard_`, `…_account_test.php` load the parts; `…_assembly_test.php` the main file: offered without the shop, pages in order and equal to `_test_rc_page_keys()`, widgets placed once, placeholders, folders, search sections, `prc-` rules, four comment threads, samples seeded, Google reviews.
- In the shared lists of the collection-form (`blog_new`, `job_new`, `staff_entry`), account-email, submitter-name, consent-privacy (`rc-app-consent`, `rc-enq-consent`) and faded-text tests.
