---
name: design-template-filmstudio
description: Film Studio template (pinegrap-filmstudio): showreel, services, projects as a collection by industry and service, studio hire, crew, project enquiries, crew calls, no shop, pfm- look, tests.
---

## What does it ship?
- Public: `home`, `services`, a `service_<key>` each, `process`, `studio`, `crew`, `about`, `faq`; `projects` (`?industry=`, `?service=`), `project`, `project_new`; `enquiry` (`?service=`) with its view, own and staff lists; `studio_hire` (`?space=`) likewise; `privacy`, `email_reply`.
- `careers`, `job`, `job_new`, `application` (`?job=`) with its view, own and staff lists; the standard pages with the Journal as the blog, account and staff pages. Full list: `_test_fm_page_keys()`.
- No shop and no catalog: only `address_book` carries `requires => ecommerce`, as Law and Construction.
- Shared: account_bar, notice, header, footer, staff_bar, enquiry_band, email_header/footer. Order 127, icon `bi-film`, look `bold`, palette `midnight`. Contact group `newsletter` (Film Studio Newsletter). Built in #7652 to #8126; 2.0.0 puts the home, services and project page on the Film Studio blocks ([[design-template-filmstudio-pages]]).

## Why a main file plus a folder?
- As Hotel: `pinegrap-filmstudio.php` includes `parts.php`, `mail.php`, then `pages_public`, `projects`, `enquiries`, `careers`, `standard`, `account` in one scope, returns kept as `$…_part` (`$services`, `$projects`, `$spaces`, `$crew`, `$jobs`, `$testimonials` are parts.php's data).

## How does it work without new product behaviour?
- The showreel is a `video` element with a poster (the Courses lesson player); the studio puts its own file in, or swaps it for its host's embed.
- Projects: a collection ([[collection-fields]]) with industry, service and status pick lists, `still_1`-`still_4`, the film's address drawn as a link in a new window (never an embed from a record value; [[bound-href-scheme]]), credits as long text, the client's words, office field `featured`.
- A project enquiry, a studio hire request and an application are requests, never a contract or a booking: pick lists by `_cf.url_param`, the hire date with `min_date` today, consent linking `privacy`, office-only status, messages on the record page's Comments widget, `submitter_only` view, the account's and staff's lists. Crew calls are the Jobs collection.
- Services, spaces, crew, why us and the figures are fixed page content from parts.php's data.
- Not built: a client review area with time-coded notes, master delivery, a live studio calendar, a cost calculator, embedding a film from a record's address.

## What is the pfm- look?
- `pfm-brand`, `pfm-display` (condensed capitals, never on a typed name), `pfm-eyebrow`, `pfm-hero` (a radial primary wash), `pfm-lift`, `pfm-play` (the play mark over a still, grows on the card's hover), `pfm-quote`, `pfm-stat` (tabular figures); Oswald headings, Inter body. Field and section ids `fm-` (never `fs-`). 16:9 card stills, the running time on each card; no fixed date or year in the sample; no `opacity-*` on text.
- Turkish: "proje", "hizmet", "proje talebi", "stüdyo kiralama", "ekip", "ekip ilanı", "başvuru", "tanıtım filmi", "Günce"; the template is "Film Stüdyosu".

## Which tests cover it?
- `design_template_filmstudio_test.php` (data, shared parts, `_test_fm_page_keys()`) and the per-part tests; `…_assembly_test.php`: page order equal to the plan's pages, widgets placed once, placeholders, `requires`, search sections, `pfm-` rules, comment threads, samples, Google reviews, the blocks' rules and motion.
- In the shared lists of the collection-form (`blog_new`, `job_new`, `project_new`, `staff_entry`), account-email, submitter-name, consent-privacy (`fm-enq-consent`, `fm-hire-consent`, `fm-app-consent`), faded-text and custom-form-rules (`fm-hire-date`) tests; the url-params test covers every template on its own.
