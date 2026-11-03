---
name: subscriptions-operator-words-errors
description: Operator Subscriptions screen: staff event words, the Event type column, 404 and the not_found move error, the one-line next-charge range on a phone (#5744, #5745, #5746)
module: subscriptions_operator
---

## Which words does the history show staff?
- `pg_subscription_operator_event_label()`: the customer's words (`pg_subscription_customer_event_label()`) except `confirm_needed`, which reads "Payment awaits the customer's confirmation". The customer's "Payment needs your confirmation" was shown to staff before (#5744).
- The history column header is `Event type`, its own key: `Event` is the calendar's ("Etkinlik").
- Turkish: `Resume` is "Devam ettir" everywhere, matching "Devam ettirildi" and the resumed notice; `Every Four Weeks` is "Her Dört Haftada Bir".

## What happens with a missing subscription?
- `view_subscriptions.php?id=<missing>` answers 404 (`output_error(…, 404)`), like `view_order.php`.
- A pause/resume/cancel POST whose result is `not_found` redirects to the list, not to `?id=`: the list prints the liveform error and clears it. Before, the error page never printed it and it came up on a later list visit (#5745).

## How does the next-charge range fit a phone?
- The two date boxes and the dash sit in one `d-inline-flex flex-nowrap … max-w-100` span; the boxes carry `flex-fill min-w-0` (copied to the picker's text box) and shrink instead of wrapping. Wrapping left the dash after the first date and the second date alone below it at 375 px (#5746). `tests/panel_narrow_layout_test.php` checks it.

Related: commerce/subscriptions-operator-screen.md
