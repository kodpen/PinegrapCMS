---
name: gift-card-email-variables
description: The product screen's Email Gift Card variable buttons (^^code^^, ^^amount^^ …): where the list lives and how it stays in step with the sender (#4449)
---

# Gift card e-mail variables on the product screen

## What does the screen show?

Under the gift card e-mail's Subject box and under the plain-text Body
(the "Insert a variable" heading) one button per variable:
`^^code^^`, `^^amount^^`, `^^from_name^^`, `^^recipient_email_address^^`,
`^^message^^`, `^^delivery_date^^`. A click inserts the token at the caret
of the box above (`product_builder.js`, `.pg-pb-giftvar` handler).

## Where does the list live?

`pg_pb_gift_card_variable_names()` and `pg_pb_gift_card_variable_buttons()`
in `product_builder.php`. The names are the `$fields` the gift card e-mail
replaces in `includes/fn/order_completion.php`; a new field there must be
added to the list too. `tests/gift_card_email_variables_test.php` checks
each listed name against the sender's source.

## What did it fix?

Before #4449 `$output_gift_card_variables` was set to `''`, so the heading
stood over nothing and an operator could send a gift card e-mail without
the card code.
