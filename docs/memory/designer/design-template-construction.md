---
name: design-template-construction
description: Construction Firm template (pinegrap-construction): services, a projects portfolio collection, quote requests and careers followed from the account, News, no shop, pcn- look, tests.
---

## What does it ship?
- Home (places `home_projects`, `google_reviews`, `home_posts`), `services` and six `service_<key>`, `sectors`, `process`, `safety`, `about`, `team`, `faq`; `projects` (`?sector=`, `?service=`), the `project` record page, `project_new` (staff); `quote` (`?service=`), `quote_view`, `my_quotes`, `staff_quotes`, `privacy`, `email_reply`; `careers`, `job` (apply link `?job=`), `job_new`, `application`, `application_view`, `my_applications`, `staff_applications`; the standard pages with the blog called News ([[design-templates-standard-pages]]); sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`; the e-mail pages.
- No shop and no catalog: a contractor sells nothing online (only `address_book` carries `requires => ecommerce`). No newsletter band: the quote band sits above the footer, which links the newsletter page.
- Shared: account_bar, notice, header, footer, staff_bar, quote_band, email_header/footer. Order 89, icon `bi-bricks`, look `bold`, palette `amber` (e-mail amber `#b45309`). Contact group `newsletter`. Built in #7543, #7575, #7614, #7633, #7679, #7710, #7731 and the assembly PR.

## Why a main file plus a folder?
- As Travel ([[design-template-travel]]): `pinegrap-construction.php` includes `parts.php`, `mail.php`, then `pages_public`, `projects`, `quotes`, `careers`, `standard`, `account` in one scope, returns kept as `$…_part`. `careers.php` uses `$cn_page_link` from `projects.php` and `$detail`, `$bound`, `$mail_reference`, `$mail_new_account` from `quotes.php`; `account.php` draws the staff area's newest requests with quotes.php's `$quote_list`.
- `email_reply` (widget `email_comment`) and `privacy` are `quotes.php`'s; `standard.php` does not define them again.

## How does it work without new product behaviour?
- Projects are a collection ([[collection-fields]]): sector and service pick lists, location, status as words ("Completed", "In progress", never a year), size and duration as words, image kinds `photo_1`-`photo_3`, scope one line per item, the client's words, office field `featured`.
- A quote request is a request, never a contract: Quote Requests record (service by `?service=` through `_cf.url_param`), plans and photos as files in `private`, consent linking `privacy`, office-only status, messages on the record page's Comments widget, `submitter_only` view. Careers as Law: Jobs collection, Applications form with a CV.
- Not built: a cost calculator, client project tracking (stages, site diary), a subcontractor portal, tender documents behind a login.

## What is the pcn- look?
- `pcn-brand`, `pcn-display`, `pcn-eyebrow`, `pcn-hero` (a primary bar along its top), `pcn-lift`, `pcn-quote`, `pcn-stat`; Archivo headings, Inter body. Field ids `cn-`.
- Turkish: "proje", "hizmet", "teklif talebi", "keşif", "iş ilanı", "başvuru" (shown as "Job Application(s)": a bare "Application" reads as software), "Haberler". Shared keys reading as a law office ("Büro") are not reused.

## Which tests cover it?
- `design_template_construction_test.php` (data, shared parts, `_test_cn_page_keys()`) and `…_public_`, `…_projects_`, `…_quotes_`, `…_careers_`, `…_standard_`, `…_account_test.php` (with every part loaded: every link and placed widget resolves); `…_assembly_test.php`: offered without the shop and with no catalog, pages in order, widgets once and placed, placeholders, only `address_book` needs the shop, contact search sections, `pcn-` rules, four comment widgets and the reply e-mail card, samples kept, Google reviews.
- In the shared lists of the collection-form (`blog_new`, `job_new`, `project_new`, `staff_entry`), account-email, submitter-name, consent-privacy (`cn-qt-consent`, `cn-app-consent`) and faded-text tests.
