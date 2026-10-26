---
name: widget-entity-fields-list
description: W8, the rest of the owner's entity allow-list (2026-10-26) still to build per family (order line, addresses, account; the order is built) and the columns kept off it.
---

## What is still to build (one slice per entity family)?
- Order line (the order view's item loop; the order itself is built, [[widget-entity-fields]]): quantity shipped, line VAT, subscription period, payments and first payment date, event name and date; yes/no added or discounted by an offer; the line's product values above.
- Ship-to and address book: address name, salutation, first and last name, address lines 1-2, city, state, zip, country, address type (words), shipped and delivered dates.
- Account (my account, profile, login region, account e-mail; the visitor's own): reward points, membership expiry, company, title, nickname, website, mobile, work and home phone, business city and country, time zone, salutation, tax number, tax office, affiliate code (`get_my_account.php` gives the classic layout these).
- Off the list: the product's notes, receipt BCC, commission, code, SEO analysis, marketplace and accounting ids; the group's price rules, code, SEO analysis, creator; the event's staff, next page, unpublish day, internal recurrence; the submitter's IP, editing staff and contact card. Compare-at price has its own plan. Reviews: nothing new.
