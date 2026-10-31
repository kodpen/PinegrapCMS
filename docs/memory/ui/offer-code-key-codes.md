---
name: offer-code-key-codes
description: A saved offer's code change moves its key codes to the new code (unless another offer keeps the old one); the editor warns while the code changes; All Key Codes names an orphan
---

## What happens to key codes when a saved offer's code changes?
- `key_codes.offer_code` points at an offer by its code, so `_pg_offer_save()` (`edit_offer_f.php`) moves them. For a saved offer whose stored code (`$existing['code']`) differs from the new one, when no other offer still has the old code, it runs `UPDATE key_codes SET offer_code = new WHERE offer_code = old` after the offers row is written. The count goes to the Site Log and into the audit after-state as `key_codes_moved`.
- Offers may share a code (`api/api-catalogue-writes.md`): while another offer keeps the old code, the key codes stay with it.
- `orders.special_offer_code` is never touched: past orders keep the code they used.

## Why?
- A changed code used to orphan every generated, imported or affiliate key code (affiliate codes carry the group offer's code). Checkout's `LEFT JOIN offers ON key_codes.offer_code = offers.code` found no offer and refused them, the editor counted 0 key codes, and Edit Key Code refused to save one until its offer code was retyped.

## What does the editor show?
- Under the code field, `#pg-if-code-follow` (`renderCodeFollow()` in `offer_editor.js`, text `L.keyCodesFollow`): shown while the typed code differs from `C.savedCode`, `C.key_code_count > 0` and `C.same_code_count` is 0.

## What does All Key Codes show for an orphan?
- `pg_key_code_list_cells()`: a key code whose offer code matches no offer (`offer_status` null from the LEFT JOIN) reads No offer has this code, not Offer not running (`list-status-words.md`).

## Which paths can change an offer's code?
- Only the offer editor; the external API and Workspace cannot.

Test: `tests/offer_code_key_codes_follow_test.php`.

Related: docs/memory/ui/offer-editor.md, docs/memory/ui/list-status-words.md
