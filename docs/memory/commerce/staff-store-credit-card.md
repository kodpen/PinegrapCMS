---
name: staff-store-credit-card
description: Store Credit card on Edit User: the answer printed on the card, typed values kept, Void asked after the reason check, the Voided state and its reason (#9008)
module: store_credit_staff
---

## Where is the answer of Issue Credit or Void shown?
- At the top of the card, where `edit_user.php?id=…#store_credit` opens: the card has its own liveform, `edit_user_store_credit`, handed to `pg_credit_staff_panel_html($user_id, $send_to, $now, $liveform)` only when its stored `id` is this account. Before #9091 it went into the `edit_user` liveform and was printed at the top of the page, about 700 px above the card (#9008).
- After a refusal the posted values are back (`add_fields_to_session()`): amount, last day and reason in the issue form (`id="store_credit_issue"`), or the void reason on the row of that card (`id="store_credit_void_<card id>"`). `pg_credit_staff_post()` returns `field`, the posted field the refusal is about (`pg_credit_staff_issue_field()` for the issue codes); it gets `is-invalid` and the inline message.

## When does Void ask?
- On submit, so the browser has checked the required reason first: the form carries `data-pg-confirm` (with `data-pg-confirm-title` / `-ok`), asked by `window.pgConfirmForm()` in the panel dialog (`pgConfirm()`) from the same submit hook as `pgConfirmTicked()` (`assets/js/backend.src.js`). A button's `data-confirm-content` is asked on click, before that check, with native `confirm()`.

## How is a voided card told from a spent one?
- `pg_credit_void()` writes the balance 0 and adds "Store credit voided on <Y-m-d>: <reason>" to the card's notes. `pg_credit_staff_cards()` reads it back as `void_reason` (`pg_credit_staff_void_reason()`, pure: the last such line, in English or the panel's language, `pg_credit_staff_void_lines()`); `pg_credit_staff_card_state()` then answers `voided` for a card with no balance left, before the expiry check. The row shows Voided and the reason under the badge.
- Not the audit trail: privacy retention prunes it, the notes stay with the card. No column holds the void.

## Tests?
- `tests/store_credit_staff_test.php`: the Voided state, the reason in both languages, the refused field.
