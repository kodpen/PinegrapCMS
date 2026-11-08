---
name: design-template-pharmacy
description: Pharmacy template (pinegrap-pharmacy): services, health checks, on duty, pharmacists, prescription requests and questions, health products shop, Health Advice by topic, prx- look, tests.
---

## What does it ship?
- Home (`home_team`, `google_reviews`, `home_posts`), `services` and a page per service (`service_prescriptions`, `service_vaccinations`, `service_health_checks`, `service_medicine_review`, `service_travel_health`, `service_delivery`), `health_checks`, `on_duty`, `about`, `faq`, `location`; `team`, the `pharmacist` record page, `pharmacist_new` (staff), `book_online`; `prescription`, `prescription_view`, `my_prescriptions`, `staff_prescriptions`, `ask`, `ask_view`, `my_questions`, `staff_questions`, `privacy`, `email_reply`; shop pages (`shop`, product, cart, checkout, order, `email_order`); the standard pages with the blog called Health Advice ([[design-templates-standard-pages]]); sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`; the e-mail pages.
- Built in #7838, #7909, #7928, #7984, #8005, #8025, #8051 and the assembly PR.

## Why a main file plus a folder?
- As Gym ([[design-template-gym]]): `pinegrap-pharmacy.php` includes `parts.php`, `mail.php`, then `pages_public.php`, `team.php`, `requests.php`, `shop.php`, `standard.php`, `account.php` in one scope, each return kept as `$…_part` (`parts.php` keeps the data in `$services`, `$checks`, `$pharmacists`, `$ranges`, `$topics`).

## Which shipped features does it reuse? (no new schema, module or behaviour)
- Prescription requests: the request pattern (`?service=`, `?pharmacist=`), an office-only status, messages on the record page's Comments widget, `submitter_only` view, My Prescriptions, a staff list. The page says the original prescription is shown at the counter.
- Questions to the pharmacist: a second request form (`?topic=`), private replies, My Questions, a staff list.
- `book_online` places the booking widget's starter (vaccinations and health checks are booking services) beside links to `prescription` and `on_duty`; `privacy` is named `lang('privacy-policy')` ([[booking-templates]]).
- Pharmacists are a collection, filtered by `?service=`. Health Advice filters by `?topic=`: the article form's `topic` pick list answers are `$topic_titles`, and the article's question link carries its topic.
- Health products only in the shop, never prescription medicine; only shop pages and `address_book` carry `requires => ecommerce`; shared parts never link them.
- Not built: patient record, medication history, e-prescription lookup, refill reminders, a duty rota by date.

## What is the prx- look?
- `prx-brand`, `prx-cross`, `prx-display`, `prx-eyebrow`, `prx-hero`, `prx-lift`, `prx-price`; field ids `rx-`. Lexend headings, Source Sans 3 body. Look `modern-soft`, palette `ocean` (the e-mails' teal), order 111, icon `bi-capsule`. No `_fx` motion: a quiet sector ([[design-template-own-look]]); its own-look polishing waits in `plans/design-template-own-looks-order.md`.
- Turkish: "eczane", "eczacı", "reçete", "nöbet"/"nöbetçi eczane", "aşı", "sağlık ölçümü", "Sağlık Önerileri".

## Which tests cover it?
- `design_template_pharmacy_test.php` (data, shared parts, `_test_rx_page_keys()`, `_test_rx_shop_keys()`) and `…_public_`, `…_team_`, `…_requests_`, `…_shop_`, `…_standard_`, `…_account_test.php` load the parts; `…_assembly_test.php` the main file: offered without a shop requirement, a unique order, catalog, pages in order and all in the parts' key list, only shop pages and `address_book` need the shop, widgets once and all placed, search sections, `prx-` classes equal rules, no motion, the four comment threads and the reply e-mail card, Google reviews, sample pharmacists and the catalog kept.
- In the shared lists of the collection-form (`pharmacist_new`), account-email, submitter-name, consent-privacy (`rx-pr-consent`, `rx-aq-consent`), faded-text and booking-templates (`prescription` ↔ `book_online`) tests.
