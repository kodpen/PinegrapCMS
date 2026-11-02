---
name: design-template-realestate
description: Plan for the Real Estate Agency template (pinegrap-realestate): listings as a collection, neighbourhoods, agents, viewing and valuation requests followed from the account; parts in a folder; steps 1-7
---

## What is being built?
- A real estate agency: home with a property search, listings to buy and to rent, one page per listing, neighbourhoods (one page each, `area_<key>`, five), agents, services (sell, let, manage), about, FAQ, a buyer's guide; viewing and valuation requests the client follows from their account; the standard pages and e-mails. Id `pinegrap-realestate`, 1.0.0, Bootstrap 5.3.8, every word through `lang()`. No shop: a house is not bought through a cart.

## How do listings work without new product behaviour?
- A listing is a record of the Listings form, a collection ([[collection-fields]]): title, deal (For sale, For rent), type, neighbourhood, status (Available, Under offer, Sold, Let), price, bedrooms, bathrooms and size as number kinds, cover and two more photos as image kinds, features, description, agent. Staff add them on `listing_new`; six samples are seeded.
- Lists are the form list widget with URL filters (`?deal=`, `?type=`, `?area=`) and number sorts ([[collection-lists]]); the listing page is the record view.
- A viewing request is a record of the Viewings form, as the Law consultation ([[design-template-law]]): the listing chosen by `?listing=` (`_cf.url_param`), day and time of day, contact way, consent; status office-use-only; messages are the record page's Comments widget. A valuation request (owner selling or letting) the same way.
- Listing alerts: the newsletter's contact group (`listing_alerts`), sent by hand from the panel.
- Not built (new behaviour): a live map, saved searches with automatic alerts, a mortgage calculator script, online offers or payments.

## Why parts in a folder?
- As Law and Salon: steps 1-6 write `includes/design_templates/pinegrap-realestate/` (not globbed); step 7's `pinegrap-realestate.php` includes them in one scope. Classes `pre-`, field ids `re-`.

## Which steps are there?
- 1 Data and shared parts: `parts.php` (builders, neighbourhoods, property types, agents, figures, services, sample listings, the forms' pick lists, account bar, notice, header, footer, valuation band, listing card, agent card, staff bar, page shells, form fields, comment thread, newsletter line, post card, AI summary); test `design_template_realestate_test.php`.

- 2 Public pages: `pages_public.php` (home with search and featured listings, neighbourhoods, five `area_<key>`, agents, services, about, FAQ, buyer's guide).

- 3 Listings: `listings.php` (`listing_new` with the Listings collection and its samples, `buy`, `rent`, `listings` with filters and sorts, `listing` record page with photos, facts, features, agent and the viewing button, the list widgets the home and area pages place).

- 4 Requests: `mail.php`, `requests.php` (`viewing`, `viewing_view`, `my_viewings`, `staff_viewings`, `valuation`, `valuation_view`, `my_valuations`, `staff_valuations`, `privacy`, `email_reply`, their widgets and notify e-mails).

- 5 Standard: `standard.php` (contact, inbox, conversation, newsletter as listing alerts, blog as "Market News", blog post, search, error); test `…_standard_test.php`.

- 6 Account and staff: `account.php` (sign-in pages, My Account, `staff`, `blog_new` with sample posts, `staff_directory`, `staff_entry`, account e-mails); test `…_account_test.php`.

- 7 Assembly: `pinegrap-realestate.php` (order 69, icon `bi-house-door`, look, palette, contact group `listing_alerts`, folders, `pre-` User Styles with two fonts, search map), the shared tests' lists, `designer/design-template-realestate.md`, plan deleted; PR body names `[NEW] Added the Real Estate Agency design template`.

## Where to start?
- `pinegrap-law/` (same layout and request pattern), [[design-templates-file-format]], [[design-template-threads]], [[collection-template-refs]].
- The next free step is the lowest one not marked done here.
