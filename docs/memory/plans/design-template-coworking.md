---
name: design-template-coworking
description: Plan: the Coworking design template (pinegrap-coworking): desks, offices and meeting rooms, memberships and day passes, tours, community events; ~1,000-line steps
---

## What is it?
- A design template `pinegrap-coworking` for a coworking space: home, spaces (hot desks, fixed desks, private offices, meeting rooms, the event hall), membership plans and day passes, amenities, community events, tour requests, meeting room bookings, memberships and passes sold in the shop, a members' area, contact, blog, sign-in and a staff area.
- Built like Gym and Hotel: `pinegrap-coworking.php` plus the folder `pinegrap-coworking/` (`parts.php`, `mail.php`, `pages_public.php`, `spaces.php`, `requests.php`, `events.php`, `members.php`, `standard.php`, `account.php`), each page file returns `pages` and `widgets`, kept as `$…_part` in the main file ([[design-template-hotel]]).

## Design
- What it reuses, what it does not build, the look and the Turkish words: [[design-template-coworking-design]].

## Steps (claim as `[plan:design-template-coworking] <n> — <topic>`; each waits for the one before it, Working mode 1)
- 1: `parts.php` (node builders, the space's data: opening hours, floors, space kinds, spaces with seats and rates, plans and day passes with prices, amenities, perks, events, members' quotes, pick lists; shared parts: account bar, notice, header, footer, tour band, staff bar; space, plan, amenity, event and quote cards, form fields, comment thread, blog parts); `tests/design_template_coworking_test.php`.

- 2: `pages_public.php`: home, `plans`, `amenities`, `about`, `faq`, `location`; `tests/design_template_coworking_public_test.php`.

- 3: `spaces.php`: the Spaces collection (`spaces` filtered by `?kind=`, `space`, `space_new`) and `book_online` (booking widget starter for meeting rooms, privacy link); test.

- 4: `mail.php` (the e-mail builders) and `requests.php`: tour requests (`tour`, `tour_view`, `my_tours`, `staff_tours`, `privacy`, `email_reply`, `email_tour_new`, `email_tour_received`) and office enquiries (`office_enquiry`, `enquiry_view`, `staff_enquiries`, `email_enquiry_new`, `email_enquiry_received`); test.

- 5: `events.php`: the Events collection the Café way (`events` from today on, `event`, `event_new`, sample events dated from today) and the sign-up (`event_signup`, `signup_view`, `my_signups`, `staff_signups`, two e-mails); test.

- 6: `members.php`: shop pages (`memberships`, product, cart, checkout, order, `email_order`, the catalog with membership products and day passes) and the members' area (`members`, `house_guide`); test.

- 7: `standard.php`: contact, inbox, conversation, newsletter, blog, blog post, help search, error ([[design-templates-standard-pages]]); test.

- 8: `account.php`: sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`, the e-mail pages; test.

- 9: assembly: `pinegrap-coworking.php` (order, search sections, look, fonts, `pcw-` rules, folders, contact group, shared parts), `…_assembly_test.php`, the template in the shared test lists (collection-form, account-email, submitter-name, consent-privacy, faded-text, booking-templates), `designer/design-template-coworking.md`; the PR body names the `[NEW]` line; this plan is deleted.

## Where does the next session start?
- Step 1. Copy the node builders, form fields, comment thread and blog parts from `pinegrap-gym/parts.php`; the data and cards are the coworking space's own.
- Translation trap: an existing key may carry another template's word (`Rooms` may read "Odalar" as a hotel's): check it before reuse.
- PHP 7.1 traps: no trailing comma after a call's last argument. Commit with committer `Erdal Güral`, or the branch guard fails.

## Open questions
- None: everything above reuses shipped features. A step that finds it needs new behaviour asks the architect (Working mode 10).
