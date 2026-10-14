---
name: signature-field-evidence
description: Signature form field (form_fields.type 'signature'): legal status, seal vs document hash, and the immutable capture and storage rules.
---

## What is the signature field and is it a qualified e-signature?
- Core `includes/fn/signature.php`; capture `assets/js/signature_pad.js`; type `form_fields.type = 'signature'` (custom forms only; `view_fields.php` / `add_field.php` / `edit_field.php`).
- It is NOT a qualified e-signature (e-imza) and does not replace a wet signature under Law 5070. It is an ordinary electronic signature; its value is entirely the record stored next to it. Never write on screen or in a document that it is "legally the same as a wet signature".

## Which two questions does it answer, and with what?
| Question | Answer |
|---|---|
| Was this record edited later? | `seal` (HMAC, `ENCRYPTION_KEY`) -> `pg_signature_verify()` |
| Did the signed document change later? | `document_hash` -> `pg_signature_document_matches()` |
- The seal alone is not enough: editing the contract-text `information` field in the panel (no DB touch) moves the signature under another document.
- The hash covers all field definitions (labels and `information` contents) and the given answers; the signature's own value is outside it.
- Everything in the hash must be readable back from `form_data`: verification re-reads answers and recomputes, and any byte difference from signing time makes every document show "changed". When adding to the hash, measure the round trip with a real record.

## Invariants
- Lock is in the schema: `UNIQUE (form_id, form_field_id)`; no second signature for the same field of a submission.
- A signature is displayed, never submitted: `edit_submitted_form.php` draws no control for it (an editable signature is not evidence). A new screen showing it calls `pg_signature_display()`.
- Time, address and identity never come from the request: `signed_at` is server time; address via `waf_client_ip()` (behind a CDN `REMOTE_ADDR` is the edge).
- The browser PNG is re-encoded through GD (`pg_signature_png_from_data_url`): the data URL is attacker-controlled and a PNG+PHP polyglot stops being both after GD. Size is rejected before decoding.
- The image is a `files` row (`attachment = 1`, `optimized = 1`); `form_data` points to it by `file_id`. Never write base64 into `form_data.data` (that column is searched content).
- `strokes` are stored: order and timing of movement is the only thing separating a pasted picture from a real signature.
- The layout generator emits a call, not markup: `<?=pg_signature_field($custom_form_fields['<id>'])?>`. The pad is canvas + hidden input + button + script agreeing; markup would freeze an old copy of that trio on every page.
- `touch-action: none` on the canvas is required: otherwise dragging on a phone scrolls the page and nothing draws.
- The field label is the consent sentence, stored as `consent_text`. Long text goes in a separate `information` field (also hashed).
- `pg_signature_ready()` probes the table AND the enum value (bridge rule): an install with the code but no DB upgrade never offers the field type.

Related: docs/memory/designer/signature-receipt-and-output.md
