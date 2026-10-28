---
name: gift-card-balance-edit
description: The gift card screen (edit_gift_card.php) writes the balance only when it was changed there, and only over the balance it opened with; why, and what a stale save sees
---

## When does saving the gift card screen write the balance?
- The form carries `balance_was`, the balance in minor units when the screen opened. A save whose balance equals it leaves the `balance` column alone and writes only the expiry date and the notes.
- A changed balance is written with `... WHERE id = … AND balance = <balance_was>`. When the card's balance is then not the one typed (an order or the API changed it meanwhile), nothing is saved: the field shows "The balance of this gift card changed while the screen was open: it is now …", `balance_was` becomes the current balance, and saving again writes the typed amount.
- A form opened before the field existed (no `balance_was`) writes the balance as before.
- The hidden `balance_was` field and the guard stay in `edit_gift_card.php`; the balance input itself is drawn by the shared `includes/forms/gift_card_form.php` (`ui/gift-card-shared-form.md`).

## Why? (#989, PR #1127)
- The other writers change the balance relatively or selectively: an order takes off `balance = balance - amount` (`submit_order.php`), the external API writes only the fields it is sent, the list's quick edit writes the note `AND notes = '<read>'`. This screen wrote the balance it had read on every save, so a note or a date saved after an order spent part of the card put the spent amount back.

## How was it checked?
- On the sandbox over HTTP with a signed-in session: card at 50,00, an order took 10,00, saving the note with the balance untouched kept 40,00; typing 60,00 over the stale 50,00 refused with "now ₺40,00"; saving again wrote 60,00.
- The amount in the message is plain text (`html_entity_decode()` of `pg_format_money()`): the field's inline feedback escapes it.

Related: docs/memory/ui/money-field-input-mask.md
