---
name: more-features-tables-2
description: The fourteen tables of plans/more-features.md weighed against what exists, part 2: wholesale prices, a company's buyers (owner, poll 87), tickets, the waiting list, courses, the sum
priority: 6
---

## Wholesale: price lists and their rows (2)?
- There is: `offer_conditions` (a type and two values), made plain so "a customer group" fits without schema; offer actions take a percentage or an amount off, never a fixed price; `erp_account_prices` and `erp_accounts.discount_rate` give one account its prices, and `erp_storefront_price()`.
- Without tables: group discounts and breaks are offers with the group condition (J1 to J3, as planned). Fixed prices for many customers: an account "uses the prices of" another, one column on `erp_accounts`.
- Bends: no fixed wholesale price without the ERP, none per quantity.
- View: no table, one column; WS2's resolver shrinks to the ERP's own.

## Wholesale: a company's buyers (1)?
- Owner (2026-11-16, agent channel poll 87, message 6516), asked whether a buyer's order needs an administrator's yes in the first version: "bunu mimara sor ve yapılmışken sağlam temelli yapılsın veya plandan çıksın".
- There is: `erp_accounts.contact_id` and its mirror `contacts.erp_account_id`, kept one to one by `erp_account_link_contact()`; no company entity (`contacts.company` is a text).
- Without a table: several contacts' mirror names one account; they are its buyers, its own contact the administrator. Every reader of the mirror is checked first; an order waiting for a yes is a new order state (stock, payment, expiry).
- View: out of this plan (J9 to J13, J16, WS6); a firm base is a plan of its own that starts with that census.

## Tickets: tickets (1) and a mark on a product?
- There is: a product already makes a form record at completion, one per unit with "One Form per Quantity" and that unit's answers (`pg_order_complete_line_form_create()`); a record has a unique code, fields, a view page, the list, the API. A gift card row is per unit too, but it is money.
- Without a table: a ticket is that record: its view page draws the QR, the code worked out from the record and the site's secret; check-in writes its "checked in" field by one conditional UPDATE; a cancelled order's ticket is void by reading the order at the scan.
- Bends: the record must lead back to its order line (to be confirmed in the code); staff can edit a record's fields.
- View: no table if that is confirmed; else the planned one.

## Tickets: the waiting list (1)?
- There is: `stock_notify` (product, address, user, sent): mails all waiting when stock returns, with trim, export and erasure. It knows a product only.
- Without a table: two columns on it (the event, its date); a freed place mails those waiting, first come first served.
- Bends: no place held for the first in line (T13, T14 go).
- View: no table, two columns.

## Courses: courses, lessons, progress (3)?
- There is: `menu_items` (parent, order, page) with its screen; a quiz form's score and pass percentage; a submission with member, page and time, already in lists, the API, flows and the personal data export. No mark of a person on a page exists.
- Without tables: a course's lessons are a menu; "done" is the member's complete submission of the lesson page's form (a quiz, or one button whose confirmation is the next lesson); progress, "My courses", the certificate and the learners are read from those; "opens N days after" is a setting of the lesson's block.
- Bends: a member can submit twice (read as "at least one"); `forms` needs an index on page and member; every "done" is a form record. The architect asked for a progress table.
- View: courses and lessons without tables; progress the architect's weighing.

## What is the sum?
- Planned: fourteen tables. With what exists: none; one column each on `ws_profiles` and `erp_accounts`, two on `stock_notify`, a value of `gift_cards.kind`, an index on `forms`; the buyers leave the plan.
- Each plan's files are rewritten to the architect's decision before a step starts.
