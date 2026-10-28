---
name: order-view-screen-rows
description: The panel order screen's card rows (includes/fn/order_view.php): empty rows hidden, address type, country, arrival date and contact named, the provider panel link (#2051, #2050)
module: order_view
---

## Which rows does the order screen hide?
- `pg_order_view_row($label, $value_html, $wide)` prints a "label: value" row only with a value: Custom Field #1/#2, Salutation, Company, Address 2, PO Number and Address Verified no longer appear as empty lines (#2051). The rows that always have a value (name, address, city, country, e-mail) are unchanged.

## How are stored codes named?
- Address type: `pg_order_view_address_type()` names `residential` / `business` through `lang()`, escaped; another stored value is shown capitalised and escaped (it was printed with `ucwords()` unescaped and untranslated).
- Country: `pg_order_view_country()` turns a two-letter code into its name (`pg_geo_country_name()`) in the billing card and at the end of the shipping address line; an older order that stored a name keeps it.
- Requested arrival date: `pg_order_view_arrival_names()` reads the names of the order's arrival date codes in one query (enabled row first, else the newest) and `pg_order_view_arrival_text()` prints "date (name)", the name alone for a dateless choice, or the code when its row is gone. Before, the raw code `(AT-ONCE)` was printed without escaping.
- Contact link: `pg_order_view_contact_text()` gives the contact's name, else the company, else the e-mail, else `#<id>`; the link text was the bare id when the contact had no name.

## Where does the provider button go?
- The toolbar's payment button (`view_order.php`) reads "<provider> panel" (`lang('{var:1} panel')` with `ECOMMERCE_PAYMENT_GATEWAY`) and opens the provider's merchant panel in a new tab (`target="_blank" rel="noopener noreferrer"`): the transaction page when the order has a transaction id, else the dashboard. It was labelled "View Orders" (the screen's own name) and opened a 500×500 popup (#2050; the orders list got the same in #2481).

## What is left?
- The "Withdrawal deadline" date on the legal documents card still prints in the US order (#2040 names it with the other date displays).

Test: `tests/order_view_rows_test.php`.
