---
name: design-template-appstudio
description: Plan for the Mobile App Developer template (pinegrap-appstudio): own apps and release notes as collections, support and project requests, beta sign-up; parts in a folder; steps 1-9
---

## What is being built?
- An independent mobile app developer's site: home with the apps, the app list with category and platform filters, a page per app, release notes, a beta sign-up, support requests followed from the account, client projects (services, three packages, the process), about, FAQ, press kit; the standard pages, blog "Dev Notes", e-mails. Id `pinegrap-appstudio`, 1.0.0. No shop: apps sell in the stores, a project is quoted.

## How does it work without new product behaviour?
- An app is a record of the Apps form, a collection ([[collection-fields]]): name, tagline, category, platforms, price model, version, rating (number), summary, description, features, icon and three screenshots (image kinds), `app_store_url`, `google_play_url`, privacy summary; office `featured`. Lists filter `?category=` and `?platform=` (contains: "iOS and Android" holds both); the app page is the record view. A store button leaves with an empty address (`pg-hide-if-empty`, the address as the hidden value).
- A release note is a record of the Releases form: app, version, platform, date, notes. The list filters `?app=`; the app page links it by a value link ([[record-value-page-links]]): no current-record filter exists.
- Support and project requests: own forms as the Real Estate valuation ([[design-template-realestate]]): `?app=` / `?package=` (`_cf.url_param`), office-only status, messages on the record page's Comments widget (`ticket_messages`, `project_messages`), `submitter_only` views, staff lists. A beta sign-up is a Beta Testers record; the invitation is sent by hand.
- Not built (new behaviour): live store data, crash reports, automatic TestFlight invitations, payments.

## Why parts in a folder?
- As Law, Real Estate and Portfolio: steps 1-8 write `includes/design_templates/pinegrap-appstudio/` (not globbed); step 9's main file includes them in one scope, each return kept as `$…_part`. Classes `pma-`, field ids `ma-`. Trap: `parts.php` keeps the samples in `$apps` and `$releases`; no part may return into those names.
- Turkish: `Paid` reads Ödenmiş, so the price model says `Paid app` (Ücretli).

## Which steps are there?
- 1 Done (#6160). Data and shared parts: `parts.php` (data, samples, pick lists, header, footer, cards, store buttons, form fields, comment thread, staff bar); test `design_template_appstudio_test.php`.

- 2 Public pages: `pages_public.php` (home, services with packages, process, about, FAQ, press kit).

- 3 Apps: `apps.php` (`app_new` with the Apps collection and samples, `apps` with filters, `app` record page, the home's list widgets).

- 4 Releases and beta: `releases.php` (`release_new` with the Releases collection and samples, `releases` with `?app=`, `beta`, `beta_view`, `staff_beta`).

- 5 Support: `mail.php`, `support.php` (`support`, `support_view`, `my_tickets`, `staff_tickets`, `privacy`, `email_reply`, notify e-mails).

- 6 Done (#6194). Projects: `projects.php` (`hire`, `project_view`, `my_projects`, `staff_projects`, e-mails).

- 7 Standard: `standard.php` (contact, inbox, conversation, newsletter, blog "Dev Notes", blog post, search, error); test `…_standard_test.php`.

- 8 Account and staff: `account.php` (sign-in, My Account, staff area, `blog_new` with samples, account e-mails); test `…_account_test.php`.

- 9 Assembly: `pinegrap-appstudio.php` (order 73, icon `bi-phone`, look `glass`, palette `lavender`, contact group `newsletter`, folders, `pma-` User Styles, search map), the shared tests' lists, `designer/design-template-appstudio.md`, plan deleted; PR body names `[NEW] Added the Mobile App Developer design template`.

## Where to start?
- `pinegrap-realestate/` (same layout and request pattern), [[design-templates-file-format]], [[design-template-threads]], [[collection-template-refs]]. The next free step is the lowest one not marked done here.
