---
name: design-template-law
description: Plan for the Law Firm template (pinegrap-law): practice areas, lawyers, case results, fees, a consultation request the client follows from their account, careers; parts in a folder; steps 1-6
---

## What is being built?
- A law firm (also fits a consulting firm by its words): home, practice areas, one page per area (`area_<key>`, six), lawyers, case results, fees, about, FAQ, careers; a consultation request the client follows from their account; the standard pages and e-mails ([[design-templates-standard-pages]]). Id `pinegrap-law`, 1.0.0, Bootstrap 5.3.8, every word through `lang()`. No shop: a firm sells nothing online.

## How does the consultation work without new product behaviour?
- As the Salon and Clinic booking ([[design-template-salon]]): a consultation is a record of the Consultations custom form (practice area, lawyer with "No preference", the other party for the conflict check, a summary, preferred contact and time of day, documents in the `private` folder, consent linking `privacy`). Auto-registration gives a visitor an account, so `consultation_view` and `my_consultations` show it at once.
- Status is an office-use-only field (Received, Conflict check, Scheduled, Engaged, Declined); messages are the record page's Comments widget (`consultation_messages`), mailed to the client and the firm. Staff list: `staff_consultations`.
- Links carry the choice: an area's page sends `?area=`, a lawyer's card `?lawyer=` (`_cf.url_param`).
- Careers: an Applications form (position, CV file, cover letter), `submitter_only` view, staff list `staff_applications`.
- Not built (new behaviour): live calendar slots, billing or time tracking, a document vault beyond the form's files, an e-signature step.

## Why parts in a folder?
- As Salon and Courses: steps 1-5 write `includes/design_templates/pinegrap-law/` (not globbed, so nothing is offered before step 6); step 6's `pinegrap-law.php` includes them in one scope. Classes `plw-` (Bootstrap owns `pe-`/`ps-`; `pl-` is avoided as a Bootstrap 4 name).

## Which steps are there?
- 1 Data and shared parts: `parts.php` (builders, practice areas, lawyers, figures, results, fee models, the form's pick lists, account bar, notice, header, footer with the not-legal-advice line, consultation band, staff bar, page shells, form fields, comment thread, newsletter line, post card, AI summary); test `design_template_law_test.php`.
- 2 Public pages: `pages_public.php` (home with `google_reviews` and testimonials, practice areas, six `area_<key>`, lawyers, results, fees, about, FAQ).
- 3 Consultation and careers: `mail.php`, `consultation.php` (`consultation`, `consultation_view`, `my_consultations`, `staff_consultations`, `privacy`, `careers`, `application`, `application_view`, `staff_applications`, their widgets and notify e-mails).
- 4 Standard: `standard.php` (contact, inbox, conversation, newsletter, blog as "Insights", blog post, search, error); test `…_standard_test.php`.
- 5 Account and staff: `account.php` (sign-in pages, My Account, `staff`, `blog_new` with sample posts, `staff_directory`, `staff_entry`, account e-mails); test `…_account_test.php`.
- 6 Assembly: `pinegrap-law.php` (header, order 68, icon `bi-bank`, highlights, look, palette, contact group `newsletter`, folders, `plw-` User Styles with two fonts, search map), the shared tests' lists, `designer/design-template-law.md`, plan deleted; PR body names `[NEW] Added the Law Firm design template`.

## Where to start?
- `pinegrap-salon/` (same layout, same booking pattern), `pinegrap-courses/` (the latest one), [[design-templates-file-format]], [[design-template-threads]].
- Done: step 1 (#4964), step 2 (#4978), step 3 (#4991), step 4 (#5011: `standard.php`; the reply e-mail and privacy stay in `consultation.php`). Next: step 5 (`account.php`). The next free step is the lowest one not marked done here.
