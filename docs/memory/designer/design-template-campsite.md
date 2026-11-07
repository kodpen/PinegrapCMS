---
name: design-template-campsite
description: Campsite template (pinegrap-campsite): kinds of stay, Pitches and Cabins collection, prices as words, stay requests and group enquiries, no shop, pcp- look, tests.
---

## What does it ship?
- Home (places `home_units`, `google_reviews`, `home_posts`), `stays` and one `stay_<key>` per kind (tent, touring, glamping, cabin), `facilities`, `activities`, `prices`, `location`, `rules`, `about`, `faq`; `units` (`?kind=`), the `unit` record page, `unit_new` (staff); `book` (`?unit=`, `?kind=`, `?season=`), `request_view`, `my_requests`, `staff_requests`, `privacy`, `email_reply`; `groups`, `group_enquiry` (`?kind=`), `enquiry_view`, `my_enquiries`, `staff_enquiries`; the standard pages with the blog called News ([[design-templates-standard-pages]]); sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`; the e-mail pages.
- No shop and no catalog: the park takes no payment online (only `address_book` carries `requires => ecommerce`).
- Shared: account_bar, notice, header, footer, staff_bar, stay_band, email_header/footer. Order 99, icon `bi-tree`, look `elegant` (there is no `natural` look), palette `forest` (e-mail green `#2f5d3a`). Contact group `newsletter`. Built in #7782, #7830, #7842, #7853, #7874, #7884 and the assembly PR.

## Why a main file plus a folder?
- As Construction ([[design-template-construction]]): `pinegrap-campsite.php` includes `parts.php`, `mail.php`, then `pages_public`, `units`, `stays`, `standard`, `account` in one scope, returns kept as `$…_part`. `parts.php` owns `$kinds`, `$units`, `$seasons` and the page shell `$page`, so the main file never uses those names.
- `email_reply` (widget `email_comment`) and `privacy` are `stays.php`'s; `standard.php` does not define them again.
- Search sections: the part files mark their own (`cp-` ids: `cp-stays`, `cp-prices`, `cp-groups`, `cp-your-data` …); the main file only the contact page's two. Never `?view=` (reserved, [[design-template-hotel]]).

## How does it work without new product behaviour?
- Kinds of stay are fixed data drawn as cards and a page each. Pitches and cabins are a collection ([[collection-fields]]): kind, place in the park and hook-up pick lists, `photo_1`-`photo_3`, office field `featured`.
- Seasonal prices are content: a season table (low, mid, high) with dates as words and prices through `$money()`; never a price engine.
- A stay request is a request, never a reservation: unit, kind and season through `_cf.url_param`, arrival and departure with `min_date` today, consent linking `privacy`, office-only status, messages on the record page's Comments widget, `submitter_only` view. Group enquiries alike, smaller form.
- Not built: availability, a calendar or map picker, prices per date, payment.

## What is the pcp- look?
- `pcp-brand`, `pcp-display`, `pcp-eyebrow`, `pcp-hero` (a wash of `--bs-primary-bg-subtle`), `pcp-lift`, `pcp-price`, `pcp-quote`, `pcp-stat`; Bitter headings, Source Sans 3 body. No `opacity-*` text, no fixed date or year. Field ids `cp-`.
- Turkish: "kamp alanı", "çadır yeri", "karavan yeri", "glamping çadırı", "kulübe", "konaklama talebi", "grup talebi", "sezon", "olanaklar", "Haberler". The shared "Setting" reads "Ayar", so a unit's setting is "Place in the Park" (Kamp Alanındaki Yeri); "konaklama talebi" declines, so tests match "konaklama tale".

## Which tests cover it?
- `design_template_campsite_test.php` (data, shared parts, `_test_cp_page_keys()`) and `…_public_`, `…_units_`, `…_stays_`, `…_standard_`, `…_account_test.php`; `…_assembly_test.php`: settings, pages in order, widgets placed once, placeholders, contact search sections, `pcp-` rules, comment threads and reply e-mail card, samples, Google reviews.
- In the shared lists of the collection-form (`blog_new`, `staff_entry`, `unit_new`), account-email, submitter-name, consent-privacy (`cp-rq-consent`, `cp-gr-consent`), min-date (`cp-rq-`/`cp-gr-arrival`, `-departure`) and faded-text tests; the url-params test reads every template.
