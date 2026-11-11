---
name: design-template-appstudio
description: Mobile App Developer template (pinegrap-appstudio): apps and release notes as collections, support and project requests followed from the account, beta sign-up, pma- look, tests.
---

## What does it ship?
- Home (1.1.0, PR #8642) on the App Studio blocks ([[designer-blocks-appstudio]]): sticky local bar, fan hero, bento figures, app switch with the `?category=` links, apps, release chapter, notes, platforms, packages, reviews, Dev Notes. `services` with three packages, `process`, `about`, `faq`, `press`; `apps` with `?category=` / `?platform=` filters, the `app` record page, `app_new` (staff); `releases` with `?app=`, `release_new`, `beta`, `beta_view`, `staff_beta`; `support`, `support_view`, `my_tickets`, `staff_tickets`, `privacy`; `hire`, `project_view`, `my_projects`, `staff_projects`; the standard pages with the blog called Dev Notes ([[design-templates-standard-pages]]); sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`; the e-mail pages. No shop and no catalog: apps sell in the stores, a project is quoted (only `address_book` carries `requires => ecommerce`).
- Shared: account_bar, notice, header, footer, staff_bar, project_band, email_header/footer. Order 73, icon `bi-phone`, look `glass`, palette `lavender` (the e-mails' inline violet `#7c3aed`). Contact group `newsletter` (App Newsletter); the developer sends it by hand. Built in #6160 and seven sibling PRs.

## Why a main file plus a folder?
- As Real Estate and Portfolio: `pinegrap-appstudio.php` includes `parts.php`, `mail.php`, then `pages_public.php` (returns its pages only), `apps.php`, `releases.php`, `support.php`, `projects.php`, `standard.php`, `account.php` in one scope, each return kept as `$…_part`.
- Trap: `parts.php` keeps the sample apps in `$apps` and notes in `$releases`; a part's return under those names would overwrite them (the assembly test checks).
- `support.php` owns `privacy` and `email_reply` with its `email_comment` widget.

## How does it work without new product behaviour?
- An app is a record of the Apps form, a collection ([[collection-fields]]): rating a number kind, icon and three screenshots image kinds, office field `featured`. Store buttons hide with an empty address (`pg-hide-if-empty`). A release note is a Releases record; the app page links its notes by a value link ([[record-value-page-links]]).
- Support, project and beta requests are records of their own forms as Real Estate's valuation ([[design-template-realestate]]): `?app=` / `?package=` (`_cf.url_param`), office-only status, messages on the record page's Comments widget (`ticket_messages`, `project_messages`), views `submitter_only`, staff lists. Beta invitations are sent by hand.
- Not built: live store data, crash reports, automatic TestFlight invites, payments.

## What is the pma- look?
- The block rules ship through `pg_design_template_user_styles()` with the four `appstudio-` names. `pma-brand`, `pma-display` (heading font, tighter), `pma-eyebrow`, `pma-mono` (version numbers and tools), `pma-icon` (square app-icon frame, 4rem; 6rem inside the app page's `header`), `pma-lift`; three fonts (Space Grotesk headings, Inter body, JetBrains Mono). Classes `pma-`, field ids `ma-`.
- Turkish trap: `Paid` reads Ödenmiş, so the price model says `Paid app` (Ücretli).
- No `opacity-*` on text (the faded-text test).

## Which tests cover it?
- `design_template_appstudio_test.php` (data, shared parts) and `…_public_`, `…_apps_`, `…_releases_`, `…_support_`, `…_projects_`, `…_standard_`, `…_account_test.php` load the parts; `…_assembly_test.php` the main file: offered without a catalog, pages in order, widgets once and all placed, placeholders name pages and folders, contact search sections, `pma-` classes equal rules, the four comment threads and the reply e-mail card, sample apps and release notes kept. Also in the collection-form, account-email, submitter-name, consent-privacy and faded-text lists.
