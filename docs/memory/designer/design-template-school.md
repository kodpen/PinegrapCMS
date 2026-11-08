---
name: design-template-school
description: Private School template (pinegrap-school): programmes with a page each, events collection, admission applications and visit requests followed from the account, News, no shop, psh- look, tests.
---

## What does it ship?
- Version 2.0.0: home, `programmes` and `day` on the School blocks ([[design-template-school-pages]]).
- Home (places `home_events`, `google_reviews`, `home_posts`), `programmes` and five `programme_<key>` (nursery, kindergarten, primary, clubs, summer), `day`, `approach`, `campus`, `staff_team`, `about`, `fees`, `faq`; `events` (`?programme=`), the `event` record page, `event_new` (staff); `apply` (`?programme=`), `application_view`, `my_applications`, `staff_applications`, `visit`, `staff_visits`, `privacy`, `email_reply`; the standard pages with the blog called News ([[design-templates-standard-pages]]); sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`; the e-mail pages.
- No shop and no catalog: a school takes no payment online (only `address_book` carries `requires => ecommerce`). No newsletter band: the admissions band sits above the footer, which links the newsletter page.
- Shared: account_bar, notice, header, footer, staff_bar, admissions_band, email_header/footer. Order 93, icon `bi-backpack`, look `playful`, palette `sunset` (e-mail orange `#c2410c`). Contact group `newsletter`. Built in #7582, #7625, #7645, #7702, #7764, #7776 and the assembly PR.

## Why a main file plus a folder?
- As Construction ([[design-template-construction]]): `pinegrap-school.php` includes `parts.php`, `mail.php`, then `pages_public`, `events`, `admissions`, `standard`, `account` in one scope, returns kept as `$…_part` (`$programmes`, `$events`, `$staff`, `$classes` are parts.php's data).
- `email_reply` (widget `email_comment`) and `privacy` are `admissions.php`'s; `standard.php` does not define them again. `account.php` draws the staff area's newest applications with admissions.php's `$ad_list`.

## How does it work without new product behaviour?
- Programmes and staff are fixed data drawn as cards and pages; fees are words, never a figure.
- Events are a collection ([[collection-fields]]): programme pick list or "Whole school", date and time as words, place, details, `photo_1`, office field `featured`.
- An admission application is a request, never an enrolment: programme by `?programme=` through `_cf.url_param`, documents as files in `private`, consent linking `privacy`, office-only status, messages on the record page's Comments widget, `submitter_only` view. A visit request is a smaller form with its staff list.
- Not built: fee payment, a parent portal with grades, attendance or timetables, class messaging, a calendar feed.

## What is the psh- look?
- `psh-brand`, `psh-display`, `psh-eyebrow`, `psh-hero` (a soft primary wash from the top corner), `psh-lift`, `psh-quote`, `psh-stat`; Nunito headings, Inter body. Field ids `sh-`. No fixed date or year; children are never named or pictured by face in the sample.
- Turkish: "program", "sınıf", "ön kayıt başvurusu", "okul ziyareti", "etkinlik", "öğretmen" / "kadro", "veli", "Haberler".

## Which tests cover it?
- `design_template_school_test.php` (data, shared parts, `_test_sh_page_keys()`) and `…_public_`, `…_events_`, `…_admissions_`, `…_standard_`, `…_account_test.php` (with every part loaded: every link and placed widget resolves, the page list equals what the parts carry); `…_assembly_test.php`: offered without the shop and with no catalog, pages in order, widgets once and placed, placeholders, only `address_book` needs the shop, contact search sections, `psh-` rules, three comment widgets and the reply e-mail card, sample events kept, Google reviews.
- In the shared lists of the collection-form (`blog_new`, `event_new`, `staff_entry`), account-email, submitter-name, consent-privacy (`sh-ap-consent`, `sh-vs-consent`) and faded-text tests.
