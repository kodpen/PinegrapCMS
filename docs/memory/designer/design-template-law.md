---
name: design-template-law
description: Law Firm template (pinegrap-law): practice areas, lawyers, results, fees, a consultation request with a conflict check followed from the account, careers, no shop, plw- look, tests.
---

## What does it ship?
- Home, practice areas, six `area_<key>`, lawyers, results, fees, about, FAQ, careers; `consultation`, `consultation_view`, `my_consultations`, `staff_consultations`, `privacy`, `application`, `application_view`, `staff_applications`; the standard pages with the blog called Insights ([[design-templates-standard-pages]]); sign-in and My Account pages, `staff_directory`, `staff`, `blog_new`, `staff_entry`; the e-mail pages. No shop and no catalog: a firm sells nothing online (only `address_book` carries `requires => ecommerce`).
- Shared: account_bar, notice, header, footer (with the not-legal-advice line), staff_bar, consult_band, email_header/footer. Order 68, icon `bi-bank`, look `corporate`, palette `midnight` (the e-mails' inline navy `#1e3a8a`). Contact group `newsletter` (Law Firm Newsletter). Built in #4964, #4978, #4991, #5011, #5052 and the assembly PR.

## Why a main file plus a folder?
- As Salon and Courses: `pinegrap-law.php` includes `parts.php`, `mail.php`, then `pages_public.php` (returns its pages only), `consultation.php`, `standard.php`, `account.php` in one scope, keeping each return as `$…_part`. `account.php` needs `consultation.php` first: the staff area's `staff_consultations_new` draws its `$consultation_row`.
- `email_reply` with its `email_comment` widget and the `privacy` page are `consultation.php`'s; `standard.php` does not define them again.

## How does the consultation work without new product behaviour?
- As the Salon booking ([[design-template-salon]]): a record of the Consultations form (practice area, lawyer with "No preference", the other party for the conflict check, a summary, contact way and time of day, documents in `private`, consent linking `privacy`). Auto-registration gives a visitor an account, so `consultation_view` and My Consultations show it at once.
- Status is office-use-only (Received, Conflict check, Scheduled, Engaged, Declined); messages are the record page's Comments widget (`consultation_messages`, with attachments), mailed to both sides. An area's page links `?area=`, a lawyer's card `?lawyer=` (`_cf.url_param`).
- Careers: an Applications form (position, CV, letter), `submitter_only` view, staff list `staff_applications`.

## What is deliberately not built?
- Live calendar slots, billing or time tracking, a document vault beyond the form's files, an e-signature step: new behaviour. My Account has no order history.

## What is the plw- look?
- `plw-brand`, `plw-eyebrow`, `plw-hero`, `plw-lift`; two fonts (Source Serif 4 headings, Public Sans body). `plw-` because Bootstrap owns `pe-`/`ps-` and `pl-` is a Bootstrap 4 name. Field ids use `lw-`. Links inside an alert carry `alert-link` (the shared alert-link test caught two in the comment thread once the template was offered).

## Which tests cover it?
- `design_template_law_test.php` (data, shared parts, public, consultation and careers), `…_standard_test.php`, `…_account_test.php` load the parts; `…_assembly_test.php` the main file: offered without a catalog, pages in order, widgets once and all placed, placeholders name pages and folders, contact search sections, `plw-` classes equal rules, the three comment threads and the reply e-mail card, Google Reviews after the sample testimonials. It is in the shared lists of the collection-form, account-e-mail, submitter-name, consent-privacy and faded-text tests.
