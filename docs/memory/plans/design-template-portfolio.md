---
name: design-template-portfolio
description: Plan for the Web Designer Portfolio template (pinegrap-portfolio): case studies as a collection, services and packages, a project brief followed from the account; parts in a folder; steps 1-7
---

## What is being built?
- A freelance web designer's site: home with selected work, the work list with category filters, one case-study page per project, services with three packages, the process, about (bio, skills, tools, experience), FAQ; a project brief the client follows from their account; the standard pages with the blog called Journal, and the e-mails. Id `pinegrap-portfolio`, 1.0.0, Bootstrap 5.3.8. No shop: a website is quoted, not bought through a cart.

## How does it work without new product behaviour?
- A case study is a record of the Projects form, a collection ([[collection-fields]]): title, category, client, industry, year (number kind), role, summary, challenge, approach, results, cover and two more images (image kinds), tools, live address; office `featured`. Six samples, added on `project_new`.
- Lists are the form list widget with the URL filter `?category=` ([[collection-lists]]); the case study is the record view.
- A brief is a record of the Project Briefs form ([[design-template-realestate]]'s valuation): project type (from `?type=`, `_cf.url_param`), package (from `?package=`), budget range, start, deadline, pages, brand assets (radio), goals, reference sites, files in `private`, contact way, consent linking `privacy`. Status office-use-only (Brief received, Proposal sent, In progress, Delivered, Declined); messages are the record page's Comments widget (`brief_messages`), mailed to both sides; views `submitter_only`; staff list `staff_briefs`.
- Not built (new behaviour): online quotes or payments, a file-approval flow, a call-booking calendar.
- Turkish trap: the shared keys `Process`, `Contact`, `Testimonial`, `Business` read İşlem, Kişi, Hasta Yorumu, İşyeri; the template says `How I Work`, `Get in Touch`, `Client Quote`, `Company Site`.

## Why parts in a folder?
- As Law and Real Estate: steps 1-6 write `includes/design_templates/pinegrap-portfolio/` (not globbed); step 7's `pinegrap-portfolio.php` includes them in one scope, each return kept as `$…_part`. Classes `pdp-` (the forum owns `pf-`), field ids `pd-`. Trap: `parts.php` keeps the sample case studies in `$projects`; no part may return into that name.

## Which steps are there?
- 1 Done (#6131). Data and shared parts: `parts.php` (builders, the designer's data, sample projects, pick lists, header, footer, brief band, project, package and testimonial cards, page shells, form fields, comment thread, staff bar); test `design_template_portfolio_test.php`.

- 2 Public pages: `pages_public.php` (home, services with packages, process, about, FAQ).

- 3 Work: `work.php` (`project_new` with the Projects collection and its samples, `work` with category filters, `project` record page with images, facts, the story and the brief button, the list widgets the home places).

- 4 Done (#6172). Briefs: `mail.php`, `briefs.php` (`brief`, `brief_view`, `my_briefs`, `staff_briefs`, `privacy`, `email_reply`, notify e-mails); test `…_briefs_test.php`. `mail.php` holds only the e-mail builders (frame, header, footer, heading, paragraph, button, line, box, greeting, comment card) and reads only `parts.php`, so step 6 builds its account e-mails on it.

- 5 Standard: `standard.php` (contact, inbox, conversation, newsletter, blog as "Journal", blog post, search, error); test `…_standard_test.php`.

- 6 Account and staff: `account.php` (sign-in, My Account, the staff area, `blog_new` with sample posts, account e-mails); test `…_account_test.php`.

- 7 Assembly: `pinegrap-portfolio.php` (order 72, icon `bi-vector-pen`, look `bold`, palette `graphite`, contact group `newsletter`, folders, `pdp-` User Styles with two fonts, search map), the shared tests' lists, `designer/design-template-portfolio.md`, plan deleted; PR body names `[NEW] Added the Web Designer Portfolio design template`.

## Where to start?
- `pinegrap-realestate/` (same layout, collection and request pattern), [[design-templates-file-format]], [[design-template-threads]], [[collection-template-refs]].
- The next free step is the lowest one not marked done here.
