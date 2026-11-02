---
name: decisions-made-product-19
description: Decisions list, product part 19 (2026-11-02 on): the owner answers of the 2026-11 feature plans (booking, importers, forms, mail complaints, Fediverse, stock, EU VAT)
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue and the date.

## Appointment booking: no account needed (2026-11-02, #5032)
- Owner: a visitor books with name, e-mail and phone; a signed-in account is linked; the manage link needs no account; auto-registration stays a template choice. Reason: a required account costs bookings.

## Content importers: imported authors become disabled panel users (2026-11-02, #5034)
- Owner: name and e-mail, no password, no sign-in; the record's author points at them; enabled by hand if wanted. Reason: the byline is kept and the person can become a real user later.

## Designer forms: a hidden field is not required; an unpaid record is saved (2026-11-02, #5035)
- Owner: a field hidden by `shown_when` is not required for that submission and its posted value is dropped on the server; #2450 ("a value condition never hides a required control") stays the widget rule.
- Owner: a paid form's record is saved at once, listed to staff with an "Unpaid" badge, and gets no confirmation mail until the payment lands. Reason: an abandoned payment stays visible and the answers are not lost.

## Mail deliverability: a complaint is an unsubscribe (2026-11-02, #5036)
- Owner: a spam complaint suppresses the address and sets `contacts.opt_in` to 0 with a consent-log row. Reason: lists and exports agree with the suppression, and providers rate the sender by this.

## ActivityPub: what is published (2026-11-02, #5041)
- Owner: records of the chosen forms and pages of the chosen folders; never drafts or private-folder pages. Reason: a site's posts are form records.

## Stock locations: the order's location must hold the units (2026-11-02, #5042)
- Owner: when the order's location (the shipping method's, the chosen pickup point, else the default) lacks the units, the checkout refuses as it does when the total is short; nothing is taken from another location. Reason: the promised stock is the stock at that place.

## EU VAT: a VIES-validated id is tax-exempt on its own (2026-11-02, #5047)
- Owner: an EU VAT id that VIES confirms sets the existing tax-exempt flag (reverse charge) without a confirmation box; the id and the check time are written on the order. Reason: no extra step for a B2B customer, and the accountant's proof is on the order.

## Memberships: a lifetime member stays lifetime (2026-11-02, #5058)
- Owner: when a lifetime member (empty end date) reaches a reward goal or buys a membership renewal product, the order completes, no period is added and the membership is not turned into a timed one (`pg_order_extended_until()`, `includes/fn/order_completion.php`). Reason: a renewal or a reward never shortens what the member had. Members already downgraded on running sites stay as they are.
