---
name: design-template-marina
description: Marina template (pinegrap-marina): kinds of berth, rates as words, Charter Boats and Events collections, berth requests, charter enquiries, event entries, no shop, pmn- look, tests.
---

## What does it ship?
- Home (places `home_charters`, `home_events`, `google_reviews`, `home_posts`), `berths` and one `berth_<key>` per kind (visitor, seasonal, annual, mooring, drystack), `rates`, `services`, `facilities`, `arriving`, `rules`, `about`, `faq`; `charters` (`?type=`), `boat`, `boat_new` (staff); `berth_request` (`?kind=`, `?season=`), `request_view`, `my_requests`, `staff_requests`, `privacy`, `email_reply`; `charter_enquiry` (`?boat=`), `enquiry_view`, `my_enquiries`, `staff_enquiries`; `events` (`?kind=`), `event`, `event_new`, `event_entry` (`?event=`), `entry_view`, `my_entries`, `staff_entries`; the standard pages with the blog called News ([[design-templates-standard-pages]]); sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`; the e-mail pages.
- No shop and no catalog: the harbour office takes payment on arrival (only `address_book` carries `requires => ecommerce`).
- Shared: account_bar, notice, header, footer, staff_bar, berth_band, email_header/footer. Order 105, icon `bi-life-preserver`, look `minimal`, palette `midnight` (e-mail navy `#1e3a8a`). Contact group `newsletter`. Built in #7794–#8021 and #8035.

## Why a main file plus a folder?
- As Construction ([[design-template-construction]]): `pinegrap-marina.php` includes `parts.php`, `mail.php`, then `pages_public`, `charters`, `requests`, `events`, `standard`, `account` in one scope, returns kept as `$…_part`. `parts.php` owns `$kinds`, `$boats`, `$seasons`, `$page`; `events.php` sets `$events`; the main file never uses those names.
- `charters.php` defines the list and link pieces (`$mn_page_link` …), `requests.php` the record pieces `events.php` reuses; `email_reply` and `privacy` are `requests.php`'s.
- Search sections: the part files mark their own (`mn-` ids: `mn-berths`, `mn-rates`, `mn-arriving`, `mn-charters`, `mn-events` …); the main file only the contact page's two. Never `?view=` (reserved, [[design-template-hotel]]).

## How does it work without new product behaviour?
- Kinds of berth are fixed data drawn as cards and a page each; rates are a season table (low, high) with the figure through `$money()` in words per night, month or year, never a price engine.
- Charter boats and events are collections ([[collection-fields]]) with an office field `featured`.
- A berth request is a request, never a reservation: `_cf.url_param` prefill, dates with `min_date` today, consent linking `privacy`, office-only status, messages on the record page's Comments widget, `submitter_only` view. Charter enquiries and event entries alike.
- Not built: availability, prices from boat length, payment, tide or weather feeds.

## What is the pmn- look?
- `pmn-brand`, `pmn-display`, `pmn-eyebrow`, `pmn-hero`, `pmn-lift`, `pmn-price`, `pmn-quote`, `pmn-stat`; Josefin Sans headings, Nunito Sans body. No `opacity-*` text or fixed date. Field ids `mn-`.
- Turkish: "bağlama yeri", "şamandıra", "kara park", "bağlama talebi", "kiralık tekne", "kiralama talebi", "kiralama masası" (charter desk), "kaptan", "regata".
- Own-look polishing: `plans/design-template-own-looks-order.md`.

## Which tests cover it?
- `design_template_marina_test.php` (data, shared parts, `_test_mn_page_keys()`) and `…_public_`, `…_charters_`, `…_requests_` (`_test_mnr_load()`), `…_events_` (`_test_mne_load()`), `…_standard_` (`_test_mns_load()`), `…_account_test.php`; `…_assembly_test.php` (settings, pages, widgets, placeholders, `pmn-` rules, threads, samples).
- In the shared lists of the collection-form (`blog_new`, `boat_new`, `event_new`, `staff_entry`), account-email, submitter-name, consent-privacy (`mn-rq-`, `mn-ce-`, `mn-ev-consent`), min-date (`mn-rq-`/`mn-ce-from`, `-to`) and faded-text tests; the url-params test reads every template.
