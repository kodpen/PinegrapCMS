---
name: whatsapp-offer-codes
description: WhatsApp offer codes (WA10, #9414): the offer column, shared code or a key per recipient made at enqueue, copy-code button, consent gate at queue and send, SMS box naming WhatsApp
module: whatsapp_offer
---

## What does a template's offer hold?
- `whatsapp_templates.offer` (no schema change) as JSON: mode (`shared` or `key`; empty is none), code (the offer's code), days (a key's validity, 1–365), variable (the body variable that carries the code, '' for only a copy-code button). `pg_whatsapp_offer_read()` / `_json()` / `_posted()` / `_check()` in `includes/fn/whatsapp_offer.php`.
- It is the site's own setting, never sent to Meta: a draft saves it with Save draft; a template WhatsApp holds has its own "Save offer code" and a "The code goes in" list of its variables.

## Where does the code go in a design?
- Where the chip "Offer code" (`pg_whatsapp_offer_label()`, a preset of Add variable) sits; `pg_whatsapp_design_posted()` takes its number, so moving chips keeps it right.
- A copy-code button (kind Copy code: no text, a sample code of at most 20 letters and digits, one per template) carries it too. The check refuses a copy-code button without an offer, an offer with nowhere to go, a variable the body lost, and an offer on a type other than Marketing.

## How is the code filled at the send?
- The senders never choose it: `pg_whatsapp_message_slots($components, $offer)` leaves the offer's places out (so the flow box's v1… and the cards' lists are unchanged) and `pg_whatsapp_message_components()` puts `PG_WHATSAPP_OFFER_PLACEHOLDER` there (body text, or a `copy_code` button with `coupon_code`). A copy-code button without an offer stays unsupported.
- `pg_whatsapp_enqueue()` sees the placeholder, reads the template's offer by `template_id` and calls `pg_whatsapp_offer_code_for_send()`: shared gives the offer's code; key makes a `key_codes` row (10 letters of `ABCDEFGHJKLMNPQRSTUVWXYZ23456789`, unique against key codes and offer codes, single use, report `offer_code`, expiration today + days, notes "WhatsApp template … to +…"). The offer must exist and be enabled, else the enqueue is refused.
- The code goes into the stored payload, so a retry sends the same one; the key's id into `key_code_id`. A duplicate reference returns the queued row before any key is made; a failed insert disables the key.

## Which consent gate applies?
- Owner #9087 (`workflow/decisions-made-product-48.md`): SMS marketing consent, `pg_sms_marketing_permission()`. At enqueue for a MARKETING template and for any message carrying an offer code; at the send again when the row was queued as marketing, Meta has moved its template to marketing since (`pg_whatsapp_queue_marketing()`), or it holds a key.
- All three senders (order moments, a form's message, the flow box) queue through `pg_whatsapp_enqueue()`; there is no WhatsApp campaign. The designer's test goes to the signed-in person's own number with the sample code.
- A row that ends failed disables its key; an uncertain one keeps it, as the message may have arrived.

## What does the visitor's consent box say?
- `pg_sms_opt_in_label()` (`includes/fn/sms.php`): "… by SMS and WhatsApp." while `pg_whatsapp_ready()`, else "… by SMS.". Used by billing information, express order, e-mail preferences (both templates) and the privacy form. The style designer's canvas sample keeps the SMS text.

## How was it verified?
- `tests/whatsapp_offer_test.php` (read, posted, check, key, expiration, slots and fill, gate, test send), `whatsapp_design_test.php`. No database and no Meta account in the cloud session: the key insert, the enqueue fill and a send with a coupon code were not run against MySQL or Meta.

Related: docs/memory/platform/whatsapp-outbox-queue.md, docs/memory/platform/whatsapp-template-approval.md, docs/memory/ui/key-code-shared-form.md
