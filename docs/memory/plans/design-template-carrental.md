---
name: design-template-carrental
description: Plan: the Car Rental design template (pinegrap-carrental), fleet collection, locations, booking requests with extras, deals and business rentals, in ~1,000-line steps
---

## What is it?
- A design template `pinegrap-carrental` for a car rental company: home, the fleet by car class, pick-up locations, extras, deals, rental terms, booking requests, business and long-term rental enquiries, contact, blog, sign-in and a staff area.
- Built like Dealership and Hotel: `pinegrap-carrental.php` plus the folder `pinegrap-carrental/` (`parts.php`, `mail.php`, `pages_public.php`, `fleet.php`, `bookings.php`, `business.php`, `standard.php`, `account.php`), each page file returns `pages` and `widgets`, kept as `$…_part` in the main file ([[design-template-dealership]], [[design-template-hotel]]).

## Design
- What it reuses, what it does not build, the look and the Turkish words: [[design-template-carrental-design]].

## Steps (claim as `[plan:design-template-carrental] <n> — <topic>`; each waits for the one before it, Working mode 1)
- 1 (done, #7646): `parts.php` (node builders, the company's data, pick lists, shared parts, cards, form fields, comment thread, blog parts); `tests/design_template_carrental_test.php`.
- 2 (done, #7903): `pages_public.php`: home, `locations`, `extras`, `deals`, `terms`, `about`, `faq`; `tests/design_template_carrental_public_test.php`.
- 3 (done, #7933): `fleet.php`: the Fleet collection (`fleet`, `car`, `car_new`, widgets `car_form`, `fleet_list`, `home_cars`, `car_more`, `car_detail`), lists filtered by `?class=`, `?gearbox=` (field `transmission`), `?fuel=`; `tests/design_template_carrental_fleet_test.php`.
- 4 (done, #8062): `mail.php` (the e-mail builders) and `bookings.php`: booking requests (`book`, `booking_view`, `my_bookings`, `staff_bookings`, `privacy`, `email_reply`, `email_booking_new`, `email_booking_received`); test.
- 5: `business.php`: business and long-term rentals (`business`, `business_enquiry`, `enquiry_view`, `my_enquiries`, `staff_enquiries`, `email_enquiry_new`, `email_enquiry_received`); test.
- 6: `standard.php`: contact, inbox, conversation, newsletter, blog (Road Trip Journal), blog post, help search, error ([[design-templates-standard-pages]]); test.
- 7: `account.php`: sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`, the e-mail pages; test.
- 8: assembly: `pinegrap-carrental.php` (order, search sections, look, fonts, `pcr-` rules, folders, contact group, shared parts), `…_assembly_test.php`, the template in the shared test lists (collection-form, account-email, submitter-name, consent-privacy, faded-text, url-params), `designer/design-template-carrental.md`; the PR body names the `[NEW]` line; this plan is deleted.

## Where does the next session start?
- Step 5. Copy Hotel's event enquiry; `bookings.php` holds `$detail`, `$bound`, `$detail_widget` (reads `{{tab:book}}`: give the enquiry its own), `$mail_reference`, `$mail_new_account`, `$renter_name`, `$checkboxes`; The enquiry's Rental Plan list takes `?plan=` from `$business_card` (an answer of `$plan_choices`). The reply e-mail (`email_reply`, in `bookings.php`) already names the business enquiry.
- Pick lists for step 5: `$plan_choices`, `$fleet_size_choices`, `$rental_term_choices`, `$enquiry_statuses`. `_test_cr_page_keys()` lists the enquiry e-mails.
- Translation keys already reused with another template's word were avoided by new English (`Mid-size`, `Security deposit`, `Cancellation policy`, `Contact Us`, `Booking Requests`, `Pick-up day`, `Return day`, `Deal chosen`, `Rental desk`) (avoid `Contact`, `Bookings`, `Deposit`, `Doors`, `Available`, `Choose a class…`, `Return`, `Return date`, `Deal`, `Desk`).
- PHP 7.1 traps: no trailing comma after a call's last argument. Commit with committer `Erdal Güral`, or the branch guard fails.

## Open questions
- None: everything reuses shipped features. A step that finds it needs new behaviour asks the architect (Working mode 10).
