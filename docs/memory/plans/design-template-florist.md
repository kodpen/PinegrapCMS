---
name: design-template-florist
description: Plan: the Florist design template (pinegrap-florist): bouquets, occasions, delivery, subscriptions, wedding and event flowers; ~1,000-line steps
---

## What is it?
- A design template `pinegrap-florist` for a flower shop and studio: home, bouquets, occasions, flower subscriptions, wedding and event flowers, delivery areas and times, flower care, about, FAQ, bouquet order requests with a delivery date and a card message, event enquiries and subscription requests, a wedding consultation booked at a free time, Our Work (a collection of past weddings and events), the shop, contact, blog, sign-in and a staff area.
- Built like Wedding and Gym: `pinegrap-florist.php` plus the folder `pinegrap-florist/` (`parts.php`, `mail.php`, `pages_public.php`, `work.php`, `orders.php`, `enquiries.php`, `shop.php`, `standard.php`, `account.php`), each page file returns `pages` and `widgets`, kept as `$…_part` in the main file ([[design-template-hotel]]).

## Design
- What it reuses, what it does not build, the look and the Turkish words: [[design-template-florist-design]].

## Steps (claim as `[plan:design-template-florist] <n> — <topic>`; each waits for the one before it, Working mode 1)
- 1: `parts.php` (node builders, the florist's data: occasions, bouquets, subscription plans, event services, delivery areas and times, flowers in season, care tips, sample work, testimonials, pick lists; shared parts: account bar, notice, header, footer, delivery band, staff bar; bouquet, occasion, plan, event service, delivery area, season, work and testimonial cards, form fields, comment thread, blog parts); `tests/design_template_florist_test.php`.
- 2: `pages_public.php`: home, `bouquets`, `occasions`, `subscriptions`, `events`, `delivery`, `care`, `about`, `faq`; test.
- 3: `work.php`: the Our Work collection (`work`, `work_item`, `work_new`, the home's `home_work` widget); test.
- 4: `mail.php` (the e-mail builders) and `orders.php`: bouquet order requests (`order_request` with `?bouquet=`, `?occasion=`, `request_view`, `my_requests`, `staff_requests`, `privacy`, `email_reply`, `email_request_new`, `email_request_received`); test.
- 5: `enquiries.php`: event enquiries (`event_enquiry` with `?event=`, `event_enquiry_view`, `staff_events`), subscription requests (`subscribe` with `?plan=`, `subscription_view`), `consultation` (the booking widget's starter beside the event enquiry, [[booking-templates]]), their e-mails; test.
- 6: `shop.php`: `shop`, product, cart, checkout, order, `email_order`, the catalog (bouquets by occasion, prepaid subscription bundles, vases and extras, gift cards); test.
- 7: `standard.php`: contact, inbox, conversation, newsletter, blog (Flower Journal), blog post, help search, error ([[design-templates-standard-pages]]); test.
- 8: `account.php`: sign-in and My Account pages, `staff`, `blog_new`, `staff_directory`, `staff_entry`, the e-mail pages; test.
- 9: assembly: `pinegrap-florist.php` (order, search sections, look, fonts, `pfl-` rules, folders, contact group, shared parts), `…_assembly_test.php`, the template in the shared test lists (collection-form, account-email, submitter-name, consent-privacy, faded-text, booking-templates), `designer/design-template-florist.md`; the PR body names the `[NEW]` line; this plan and its design file are deleted.

## Where does the next session start?
- Step 1. Copy the shape of `pinegrap-wedding/parts.php` and its test; keep the florist's own data, words and `pfl-` classes.
- Check an existing key's Turkish before reuse (it may carry another template's word). Add keys with `tools/tr_add.php --from=<json>`; when `development` moves, a conflict lands only in `tr/*.json`: take `development`'s shard and re-add the keys.
- PHP 7.1 traps: no trailing comma after a call's last argument. Commit with committer `Erdal Güral`, or the branch guard fails.

## Open questions
- None: everything above reuses shipped features. A step that finds it needs new behaviour asks the architect (Working mode 10).
