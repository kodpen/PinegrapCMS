---
name: design-template-cityhall
description: City Hall template (pinegrap-cityhall): services directory, Notices and Events collections, citizen and meeting requests, no shop, pcy- look, tests.
---

## What does it ship?
- Home (places `home_notices`, `home_events`, `home_posts`), `services` and one `service_<key>` per service area, `mayor`, `council`, `departments`, `city`, `service_points`, `numbers`, `faq`; `notices` (`?type=`), the `notice` record page, `notice_new`; `events` (`?category=`), `event`, `event_new` (staff); `request` (`?topic=`), `request_view`, `my_requests`, `staff_requests`; `meet`, `meeting_view`, `my_meetings`, `staff_meetings`; `privacy`, `email_reply`; the standard pages, News as the blog ([[design-templates-standard-pages]]); account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`.
- No shop and no catalog: the city hall takes no payment online (only `address_book` carries `requires => ecommerce`).
- Shared: account_bar, notice, header, footer, staff_bar, request_band, email_header/footer. Order 101 (99 is Campsite's), icon `bi-building`, look `minimal` (there is no `classic` look), palette `classic` (Classic Blue; e-mail blue `#1d4ed8`). Contact group `newsletter`. Built in #7783-#7899 and the assembly PR.

## Why a main file plus a folder?
- As Construction ([[design-template-construction]]): `pinegrap-cityhall.php` includes `parts.php`, `mail.php`, then `pages_public`, `notices`, `requests`, `standard`, `account` in one scope, returns kept as `$…_part`. `parts.php` owns `$notices`, `$events`, `$departments`, `$service_areas` and the page shell `$page`, so the main file never uses those names.
- `requests.php` uses `notices.php`'s `$cy_list`; later files reuse its `$cy_messages_settings`. `email_reply` (widget `email_comment`) and `privacy` are `requests.php`'s; `standard.php` does not define them again.
- Search sections: the part files mark their own (`cy-` ids: `cy-services`, `cy-area-<key>`, `cy-points`, `cy-your-data` …); the main file only the contact page's two. Never `?view=` (reserved).

## How does it work without new product behaviour?
- Service areas, departments, mayor, council, neighbourhoods, service points and numbers are fixed data in `parts.php`, drawn as cards; hours are words.
- Notices and events are collections ([[collection-fields]]): a type or category pick list, reference, dates as words, department, summary, details, a document or `photo_1`, office field `featured`; lists filter `?type=` / `?category=` ([[collection-lists]]).
- A citizen request is a record, never a case system: kind, topic through `_cf.url_param` (each service links `request?topic=`), neighbourhood, place as words, a photo in `private`, consent linking `privacy`; office-only status; messages on the record page's Comments widget, `submitter_only` view. Meeting requests alike, smaller form.
- Not built: payments, e-government login, case numbers, a request map, e-tender bidding.

## What is the pcy- look?
- `pcy-brand`, `pcy-display`, `pcy-eyebrow`, `pcy-hero` (a wash of `--bs-primary-bg-subtle`), `pcy-lift`, `pcy-ref` (reference numbers), `pcy-stat`; Public Sans headings, Source Sans 3 body. `pch-` is Charity's. No `opacity-*` text, no fixed date or year. Field ids `cy-`.
- Turkish: "belediye", "belediye meclisi", "müdürlük", "duyuru", "ihale", "meclis kararı", "talep" / "şikâyet", "mahalle", "hizmet noktası", "Haberler".

## Which tests cover it?
- `design_template_cityhall_test.php` (data, shared parts, `_test_cy_page_keys()`) and `…_public_`, `…_notices_`, `…_requests_`, `…_standard_`, `…_account_test.php`; `…_assembly_test.php`: settings, pages in order, widgets placed once, placeholders, contact search sections, `pcy-` rules, comment threads and reply e-mail card, samples, a page per service area.
- In the shared lists of the collection-form (`blog_new`, `event_new`, `notice_new`, `staff_entry`), account-email, submitter-name, consent-privacy (`cy-rq-consent`, `cy-mt-consent`) and faded-text tests; the url-params test reads every template.
