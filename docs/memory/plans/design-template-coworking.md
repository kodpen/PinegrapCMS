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
- 1 (done, #7660): `parts.php` (node builders, the space's data, shared parts and cards); `tests/design_template_coworking_test.php`.

- 2 (done, #7856): `pages_public.php`: home, `plans`, `amenities`, `about`, `faq`, `location`; `tests/design_template_coworking_public_test.php`.

- 3 (done, #7868): `spaces.php`: the Spaces collection (`spaces` filtered by `?kind=`, `space`, `space_new`) and `book_online` (booking widget starter for meeting rooms, privacy link); test.

- 4 (done, #7891): `mail.php` (the e-mail builders) and `requests.php`: tour requests (`tour`, `tour_view`, `my_tours`, `staff_tours`, `privacy`, `email_reply`, `email_tour_new`, `email_tour_received`) and office enquiries (`office_enquiry`, `enquiry_view`, `staff_enquiries`, `email_enquiry_new`, `email_enquiry_received`); test.

- 5 (done, #7971): `events.php`: Events collection (`events` from today on, ?topic=, `event`, `event_new`, samples dated from today on a weekday) and the sign-up (`event_signup` ?event=, `signup_view`, `my_signups`, `staff_signups`, two e-mails); test.

- 6 (done, #8002): `members.php`: shop pages (`memberships` with Memberships and Day Passes groups, product, cart, checkout, order, `email_order`) and the members' area (`members` with perks, `house_guide`); test.

- 7 (done, #8024): `standard.php`: contact, inbox, conversation, newsletter, blog ("Blog"), blog post, help search ("Help Center"), error, `email_new_message`, `email_message_received`, `email_welcome`; test.

- 8 (done, #8056): `account.php`: sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`, the e-mail pages; test.

- 9: assembly: `pinegrap-coworking.php` (order, search sections, look, fonts, `pcw-` rules, folders, contact group, shared parts), `…_assembly_test.php`, the template in the shared test lists (collection-form, account-email, submitter-name, consent-privacy, faded-text, booking-templates), `designer/design-template-coworking.md`; the PR body names the `[NEW]` line; this plan is deleted.

## Where does the next session start?
- Step 9 (start from the Gym's `pinegrap-gym.php` and its assembly test). `account.php` links `members` only with the shop. `events.php` is included after `spaces.php` and `requests.php` (it reuses their request pieces). Step 9 includes the parts in that order; `members.php` returns `catalog` too (two groups).
- The home places `home_events` (events.php).
- Translation traps: `Space` reads "Boşluk" and `Seats` "Koltuklar"; use `Workspace` and `Number of Seats`.
- PHP 7.1: no trailing comma after a call's last argument. Commit with committer `Erdal Güral`. The Turkish shards conflict often: take development's side and re-add the keys with `tools/tr_add.php --from`.

## Open questions
- None: everything above reuses shipped features. A step that finds it needs new behaviour asks the architect (Working mode 10).
