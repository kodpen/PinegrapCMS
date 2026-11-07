---
name: design-template-architecture
description: Architecture & Interiors Studio template (pinegrap-architecture): projects by type and location, enquiries, consultations and careers followed from the account, no shop, pai- look, tests.
---

## What does it ship?
- Home (places `home_projects`, `google_reviews`, `home_posts`), `services` and six `service_<key>`, `studio`, `team`, `process`, `awards`, `faq`; `projects` (`?type=`, `?location=`), the `project` record page, `project_new` (staff); `enquiry` and `consultation` (both `?service=`), their `_view` pages, `my_` and `staff_` lists, `privacy`, `email_reply`; `careers`, `job` (apply link `?job=`), `job_new`, `application` (portfolio and CV), `application_view`, `my_applications`, `staff_applications`; the standard pages with the blog called the Journal ([[design-templates-standard-pages]]); sign-in and My Account pages, `staff` (newest enquiries and consultations), `blog_new`, `staff_directory`, `staff_entry`; the e-mail pages.
- No shop and no catalog: a studio sells its time, quoted per project (only `address_book` carries `requires => ecommerce`).
- Shared: account_bar, notice, header, footer, staff_bar, enquiry_band, email_header/footer. Order 97, icon `bi-rulers`, look `minimal`, palette `graphite` (e-mail `#18181b`). Contact group `newsletter`. Built in #7639, #7767, #7777, #7790, #7802, #7814, #7828 and the assembly PR.

## Why a main file plus a folder?
- As Construction ([[design-template-construction]]): `pinegrap-architecture.php` includes `parts.php`, `mail.php`, then `pages_public`, `projects`, `enquiries`, `careers`, `standard`, `account` in one scope, returns kept as `$…_part`. `careers.php` and `account.php` use `enquiries.php`'s `$request_list`, `$detail`, `$bound`, `$mail_reference`, `$mail_new_account`.
- `email_reply` (widget `email_comment`) and `privacy` are `enquiries.php`'s; `standard.php` does not define them again.

## How does it work without new product behaviour?
- Projects are a collection ([[collection-fields]]): type and location pick lists, the stage as a pick list (never a year), setting and size as words, image kinds `photo_1`-`photo_4` drawn as the gallery, the credits one "Role: Name" line each, office field `featured`.
- A project enquiry is a request, never a contract: service by `?service=` through `_cf.url_param`, drawings and photos as files in `private`, consent linking `privacy`, office-only status, messages on the record page's Comments widget, `submitter_only` view. A consultation request is the smaller form (where to meet, preferred days as words): no calendar, the studio answers with a time. Careers as Construction.
- The team, awards and press are fixed data drawn as cards, never a collection; awards name the prize, never the year.
- Not built: a fee calculator, a client portal, mood boards, a 3D viewer, a consultation calendar.

## What is the pai- look?
- `pai-brand` (spaced capitals), `pai-display`, `pai-eyebrow`, `pai-hero` (a thin rule under it), `pai-index` (small-caps numbers of the steps and awards), `pai-lift`, `pai-quote`, `pai-stat`; Jost headings, Karla body. Square corners, no `opacity-*` text. Field ids `ai-`.
- Turkish: "proje", "hizmet", "proje talebi", "ön görüşme", "stüdyo", "ödül", "basın", "Günce" for the Journal, "iş ilanı", "başvuru".

## Which tests cover it?
- `design_template_architecture_test.php` (data, shared parts, `_test_ai_page_keys()`) and `…_public_`, `…_projects_`, `…_enquiries_`, `…_careers_`, `…_standard_`, `…_account_test.php` (every link, placed widget and page key resolves); `…_assembly_test.php`: offered with no catalog, pages in order, widgets once and placed, placeholders, only `address_book` needs the shop, contact search sections, `pai-` rules, five comment widgets and the reply e-mail card, samples kept, Google reviews.
- In the shared lists of the collection-form (`blog_new`, `job_new`, `project_new`, `staff_entry`), account-email, submitter-name, consent-privacy (`ai-enq-consent`, `ai-con-consent`, `ai-app-consent`) and faded-text tests.
