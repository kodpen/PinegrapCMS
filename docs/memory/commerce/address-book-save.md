---
name: address-book-save
description: When a shipping step copies a recipient's address into the signed-in customer's address book (pg_address_book_writable()), and how an address book edit reaches an open order
module: shipping
---

## Which paths write the address book?
- The address book screen and its widget (`update_address_book.php`), the classic shipping step (`shipping_address_and_arrival.php`, the shipping address widget posts there too), the express order (`express_order.php`, classic page and designer widget) and the order's completion with auto-registration (`pg_order_complete_ship_dates()`: only a name the account does not hold yet).
- A recipient is matched by account and ship to name; a ghosting operator writes nothing.

## When does a shipping step copy the address?
- Only a whole address: first and last name, address 1, city and country, the state and the zip code when the country asks for them (`pg_address_book_writable()` in `includes/fn/shipping.php`, the address book screen's own rule).
- The classic step also waits for a form without errors. The express order saves its recipients on every update, before any field is required, so the rule is all it asks.
- Before the bug hunt of customer accounts, a refused classic step, or an express order update with a half-typed recipient, overwrote the saved address with the broken one.

## Does an address book edit reach an open order?
- Not by itself: the order's recipient (`ship_tos`) keeps what was posted. The next time a shipping form is drawn without typed values, it is filled from the address book row of the same name (classic step, express order, shipping address widget), so the customer sees and posts the edited address.
- The district lives on the recipient only (the address book has none) and is taken from the order's recipient.
- A renamed or removed address book row stays in the session's ship to pick list until the session ends; picking it gives an empty form.

Related: docs/memory/commerce/express-order-notes-address-book.md, docs/memory/workflow/bug-hunt-areas-customer-accounts-addresses.md
