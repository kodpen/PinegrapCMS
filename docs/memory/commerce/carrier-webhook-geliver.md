---
name: carrier-webhook-geliver
description: Geliver's side of the carrier webhook (#3245): the TRACK_UPDATED message read, registration and removal, no signature, what is unverified
---

## How does the TRACK_UPDATED webhook work?
- Receiver: `carrier-webhook.md`. `_webhook_read()` takes `{event, metadata, data: <shipment>}` (the reference's test message, fixture `webhook_track_updated.json`), only `TRACK_UPDATED`, `data.id` the reference, `data.isReturn` the return flag; `_webhook_register()` `POST /webhook {type, url}`, `_webhook_remove()` `DELETE /webhook/{id}` (404 = gone).
- No signature in the reference (`headerName`/`headerValue` undocumented for create): the key in the URL is the credential.
- Tested in `tests/carrier_label_geliver_test.php` (fixture `webhook_track_updated.json`). Driver: `carrier-label-geliver.md`.
- Unverified: a live delivery, retries on a non-2xx, a query string kept in the URL.
