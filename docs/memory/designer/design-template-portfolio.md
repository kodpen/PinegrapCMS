---
name: design-template-portfolio
description: Web Designer Portfolio template (pinegrap-portfolio): case studies as a collection, services with three packages, a project brief followed from the account, pdp- look, tests.
---

## What does it ship?
- Home with selected work, `services` with three packages, `process`, `about` (story, skills, tools, experience), `faq`; `work` with `?category=` filters, the `project` case-study page, `project_new` (staff); `brief`, `brief_view`, `my_briefs`, `staff_briefs`, `privacy`; the standard pages with the blog called Journal ([[design-templates-standard-pages]]); sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`; the e-mail pages. No shop and no catalog (only `address_book` carries `requires => ecommerce`).
- Shared: account_bar, notice, header, footer, staff_bar, brief_band, email_header/footer. Order 72, icon `bi-vector-pen`, look `bold`, palette `graphite` (the e-mails' inline zinc greys, `#18181b` button). Contact group `newsletter` (Portfolio Newsletter); the designer sends new case studies and posts by hand. Built in #6131, #6169, #6168, #6172, #6176, #6177 and the assembly PR.

## Why a main file plus a folder?
- As Real Estate: `pinegrap-portfolio.php` includes `parts.php`, `mail.php`, then `pages_public.php` (returns its pages only), `work.php`, `briefs.php`, `standard.php`, `account.php` in one scope, each return kept as `$…_part`.
- Trap: `parts.php` keeps the six sample case studies in `$projects`; a part's return under that name would overwrite them (the assembly test checks every sample title survives).
- `mail.php` holds only the e-mail builders. `briefs.php` owns `privacy`, `email_reply` with its `email_comment` widget, and also the contact form's `email_new_message`, `email_message_received` and the newsletter's `email_welcome`, which `standard.php` names but does not define.

## How does it work without new product behaviour?
- A case study is a record of the Projects form, a collection ([[collection-fields]]): year a number kind, cover and two images image kinds, office field `featured`. Lists are the form list widget with `?category=` ([[collection-lists]]); the case study is the record view.
- A brief is a record of the Project Briefs form as Real Estate's valuation ([[design-template-realestate]]): project type from `?type=` and package from `?package=` (`_cf.url_param`), files in `private`, status office-use-only (Brief received, Proposal sent, In progress, Delivered, Declined), messages the record page's Comments widget (`brief_messages`), view `submitter_only`, staff list `staff_briefs` with a search.
- Not built (new behaviour): online quotes or payments, a file-approval flow, a call-booking calendar.

## What is the pdp- look?
- `pdp-brand`, `pdp-display` (heading font, tighter), `pdp-eyebrow`, `pdp-hero` (palette wash with a `color-mix()` fallback), `pdp-lift`; two fonts (Space Grotesk headings, Inter body). `pdp-` because the forum owns `pf-`; field ids use `pd-`.
- Turkish trap: the shared keys `Process`, `Contact`, `Testimonial`, `Business` read İşlem, Kişi, Hasta Yorumu, İşyeri; the template says `How I Work`, `Get in Touch`, `Client Quote`, `Company Site`.
- The brief band is `text-bg-primary`; its line is full colour, never `opacity-*` (the faded-text test).

## Which tests cover it?
- `design_template_portfolio_test.php` (data, shared parts), `…_briefs_test.php`, `…_standard_test.php`, `…_account_test.php` load the parts; `…_assembly_test.php` the main file: offered without a catalog, pages in order, widgets once and all placed, placeholders name pages and folders, contact search sections, `pdp-` classes equal rules, the three comment threads and the reply e-mail card, sample case studies kept, Google Reviews after the sample quotes. It is in the shared lists of the collection-form (`project_new`), account-email, submitter-name, consent-privacy and faded-text tests.
