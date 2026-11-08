---
name: design-template-florist
description: Plan: the Florist design template (pinegrap-florist): bouquets, occasions, delivery, subscriptions, wedding and event flowers; ~1,000-line steps
---

## What is it?
- A design template `pinegrap-florist` for a flower shop: bouquets, occasions, subscriptions, wedding and event flowers, delivery, care, order requests with a delivery date and card message, event and subscription requests, a consultation booking, Our Work (a collection), the shop, contact, blog, sign-in, staff area.
- Built like Wedding and Gym: `pinegrap-florist.php` plus the folder `pinegrap-florist/` (`parts.php`, `mail.php`, `pages_public.php`, `work.php`, `orders.php`, `enquiries.php`, `shop.php`, `standard.php`, `account.php`), each page file returns `pages` and `widgets`, kept as `$…_part` in the main file ([[design-template-hotel]]).

## Design
- What it reuses, what it does not build, the look and the Turkish words: [[design-template-florist-design]].

## Steps (claim as `[plan:design-template-florist] <n> — <topic>`; each waits for the one before it, Working mode 1)
- 1 (done, #7648): `parts.php` (builders, data, shared parts, cards, form fields, comment thread, blog parts); `tests/design_template_florist_test.php`.
- 2 (done, #7797): `pages_public.php`: home, `bouquets`, `occasions`, `subscriptions`, `events`, `delivery`, `care`, `about`, `faq`; test.
- 3 (done, #7806): `work.php`: the Our Work collection (`work`, `work_item`, `work_new`, the home's `home_work` widget); test.
- 4 (done, #7912): `mail.php` (the e-mail builders) and `orders.php`: bouquet order requests (`order_request` with `?bouquet=`, `?occasion=`, `request_view`, `my_requests`, `staff_requests`, `privacy`, `email_reply`, `email_request_new`, `email_request_received`); test.
- 5 (done, #7975): `enquiries.php`: event enquiries (`event_enquiry` with `?event=`, `event_enquiry_view`, `staff_events`), subscription requests (`subscribe` with `?plan=`, `subscription_view`), `consultation` (the booking widget's starter beside the event enquiry, [[booking-templates]]), their e-mails; test.
- 6 (done, #8007): `shop.php`: `shop`, product, cart, checkout, order, `email_order`, the catalog (bouquets by occasion, prepaid subscription bundles, vases and extras, gift cards); test.
- 7 (done, #8026): `standard.php`: contact, inbox, conversation, newsletter, blog (Flower Journal), blog post, help search, error ([[design-templates-standard-pages]]); test.
- 8: `account.php`: sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`, the e-mail pages; test.
- 9: assembly: `pinegrap-florist.php` (order, search sections, look, fonts, `pfl-` rules, folders, contact group, shared parts), `…_assembly_test.php`, the template in the shared test lists (collection-form, account-email, submitter-name, consent-privacy, faded-text, booking-templates), `designer/design-template-florist.md`; the PR body names the `[NEW]` line; this plan and its design file are deleted.

## Where does the next session start?
- Step 8. Copy `pinegrap-wedding/account.php` and its test. The other parts already link `login`, `register`, `my_account`, `forgot_password`, `staff`, `blog_new` (the post form the journal lists read: `title`, `summary`, `content`, `cover_image`), `staff_directory` and `staff_entry`; `standard.php` owns the message and newsletter e-mails (`email_new_message`, `email_message_received`, `email_welcome`) and the home's `home_posts` and `google_reviews`. My Account should link My Requests (orders, event enquiries, subscriptions) and the staff lists `staff_requests` and `staff_events`.
- Check an existing key's Turkish before reuse: `Occasions` reads "Etkinlikler", `Occasion` "Vesile" (step 4 labels it `Special Occasion`, "Özel Gün"), `The work` "Eser".
- PHP 7.1: no trailing comma after a call's last argument.

## Open questions
- None: everything above reuses shipped features. A step that finds it needs new behaviour asks the architect (Working mode 10).
