---
name: design-template-realestate
description: Real Estate Agency template (pinegrap-realestate): listings as a collection, neighbourhoods, agents, viewing and valuation requests followed from the account, pre- look, tests.
---

## What does it ship?
- Home with a property search, `buy`, `rent` and `listings` lists, the `listing` page, `neighbourhoods` and five `area_<key>`, agents, services with fees, about, FAQ, buyer's guide; `listing_new` (staff); `viewing`, `viewing_view`, `my_viewings`, `staff_viewings`, `valuation`, `valuation_view`, `my_valuations`, `staff_valuations`, `privacy`; the standard pages with the blog called Market News ([[design-templates-standard-pages]]); sign-in and My Account pages, `staff_directory`, `staff`, `blog_new`, `staff_entry`; the e-mail pages. No shop and no catalog (only `address_book` carries `requires => ecommerce`).
- Shared: account_bar, notice, header, footer, staff_bar, valuation_band, email_header/footer. Order 69, icon `bi-house-door`, look `modern-sharp`, palette `ocean` (the e-mails' inline teal `#0f766e`). Contact group `listing_alerts` (Listing Alerts), the newsletter form's group; the staff send new listings by hand. Built in #5092, #5120, #5132, #5141, #5165, #5176 and the assembly PR.

## Why a main file plus a folder?
- As Law and Salon: `pinegrap-realestate.php` includes `parts.php`, `mail.php`, then `pages_public.php` (returns its pages only), `listings.php`, `requests.php`, `standard.php`, `account.php` in one scope, each return kept as `$…_part`.
- Trap: `parts.php` keeps the six sample listings in `$listings`; a part's return named `$listings` would overwrite them before `listings.php` seeds the collection (the assembly test checks every sample title survives).
- `email_reply`, its `email_comment` widget and `privacy` are `requests.php`'s; `standard.php` does not define them again.

## How do listings work without new product behaviour?
- A listing is a record of the Listings form, a collection ([[collection-fields]]): number kinds for price, bedrooms, bathrooms, size; image kinds for the photos. Lists are the form list widget with URL filters `?deal=`, `?type=`, `?area=` ([[collection-lists]]); the listing page is the record view.
- Viewing and valuation requests: records of their own forms as the Law consultation ([[design-template-law]]); the listing comes from `?listing=` (`_cf.url_param`), status is office-use-only, messages are the record page's Comments widget (`viewing_messages`, `valuation_messages`), views `submitter_only`.

## What is deliberately not built?
- A live map, saved searches with automatic alerts, a mortgage calculator, online offers or payments: new behaviour.

## What is the pre- look?
- `pre-brand`, `pre-eyebrow`, `pre-hero`, `pre-lift`, `pre-price` (tabular figures); two fonts (Fraunces headings, Inter body). `pre-` because Bootstrap owns `pe-`; field ids use `re-`.

## Which tests cover it?
- `design_template_realestate_test.php` (data, shared parts, public, listings, requests), `…_standard_test.php`, `…_account_test.php` load the parts; `…_assembly_test.php` the main file: offered without a catalog, pages in order, widgets once and all placed, placeholders name pages and folders, contact search sections, `pre-` classes equal rules, the four comment threads and the reply e-mail card, sample listings kept, Google Reviews after the sample testimonials. It is in the shared lists of the collection-form (`listing_new` too), account-e-mail, submitter-name, consent-privacy and faded-text tests.
