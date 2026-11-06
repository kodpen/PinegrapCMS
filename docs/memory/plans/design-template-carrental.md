---
name: design-template-carrental
description: Plan: the Car Rental design template (pinegrap-carrental), fleet collection, locations, booking requests with extras, deals and business rentals, in ~1,000-line steps
---

## What is it?
- A design template `pinegrap-carrental` for a car rental company: home, the fleet by car class, pick-up locations, extras, deals, rental terms, booking requests, business and long-term rental enquiries, contact, blog, sign-in and a staff area. Not covered by `pinegrap-dealership` (selling cars, test drives, trade-ins, finance).
- Built like Dealership and Hotel: `pinegrap-carrental.php` plus the folder `pinegrap-carrental/` (`parts.php`, `mail.php`, `pages_public.php`, `fleet.php`, `bookings.php`, `business.php`, `standard.php`, `account.php`), each page file returns `pages` and `widgets`, kept as `$…_part` in the main file ([[design-template-dealership]], [[design-template-hotel]]).

## Design
- What it reuses, what it does not build, the look and the Turkish words: [[design-template-carrental-design]].

## Steps (claim as `[plan:design-template-carrental] <n> — <topic>`; each waits for the one before it, Working mode 1)
- 1: `parts.php` (node builders, the company's data: car classes, sample cars, locations, extras, deals, rental terms, team, figures, testimonials, pick lists; shared parts: account bar, notice, header, footer, booking band, staff bar; car card and facts, class tile, location, extra, deal and testimonial cards, form fields, comment thread, blog parts); `tests/design_template_carrental_test.php`.
- 2: `pages_public.php`: home, `locations`, `extras`, `deals`, `terms`, `about`, `faq`; `tests/design_template_carrental_public_test.php`.
- 3: `fleet.php`: the Fleet collection (`fleet`, `car`, `car_new`), lists filtered by `?class=`, `?gearbox=`, `?fuel=`; test.
- 4: `mail.php` (the e-mail builders) and `bookings.php`: booking requests (`book`, `booking_view`, `my_bookings`, `staff_bookings`, `privacy`, `email_reply`, `email_booking_new`, `email_booking_received`); test.
- 5: `business.php`: business and long-term rentals (`business`, `business_enquiry`, `enquiry_view`, `my_enquiries`, `staff_enquiries`, `email_enquiry_new`, `email_enquiry_received`); test.
- 6: `standard.php`: contact, inbox, conversation, newsletter, blog (Road Trip Journal), blog post, help search, error ([[design-templates-standard-pages]]); test.
- 7: `account.php`: sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`, the e-mail pages; test.
- 8: assembly: `pinegrap-carrental.php` (order, search sections, look, fonts, `pcr-` rules, folders, contact group, shared parts), `…_assembly_test.php`, the template in the shared test lists (collection-form, account-email, submitter-name, consent-privacy, faded-text, url-params), `designer/design-template-carrental.md`; the PR body names the `[NEW]` line; this plan is deleted.

## Where does the next session start?
- Step 1. Copy the node builders, form fields and comment thread from `pinegrap-dealership/parts.php`, the `$money()` price per currency from `pinegrap-hotel/parts.php`.
- PHP 7.1 traps: no trailing comma after a call's last argument. Commit with committer `Erdal Güral`, or the branch guard fails.
- Translation trap: an existing key may carry another template's word: check it in tr.json before reuse.

## Open questions
- None: everything reuses shipped features. A step that finds it needs new behaviour asks the architect (Working mode 10).
