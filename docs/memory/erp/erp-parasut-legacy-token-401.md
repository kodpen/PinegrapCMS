---
name: erp-parasut-legacy-token-401
description: The order screen's Paraşüt buttons, inbox and VKN lookup renew a token Paraşüt answers 401 for while the cache still holds it: forget, renew once, send again (#7569)
module: parasut
---

## What went wrong? (#7569)
- `parasut_get_token()` trusts the cached access token until its own `expires_at` (about 2 hours). Paraşüt retires a token earlier when another renewal with the same credentials hands out a new one (a staging copy, a re-authorization). `_parasut_request()` retried only a 429, so every press of Create Invoice or E-Shipment, the inbox lists and the VKN lookup failed with Paraşüt's 401 words until the clock ran out. The ERP driver (`erp_edoc_parasut_request()`) already renewed once.

## What does `_parasut_request()` do with a 401 now?
- `parasut_request_renewing()` sends once more with a renewed token, at most once per request; a second 401 (`No access to company: <n>`) is returned, so `parasut_stored_record_state()` still calls it `unknown` and no kept id is cleared.
- `_parasut_token_after_401()`: `parasut_unauthorized_step()` says `forget` when the cache still holds the refused token (then `parasut_token_forget()`), `renew` when it holds another or none (another request renewed or forgot it; kept), `none` without a token or a cache. Then `parasut_get_token()` under its lock.
- `parasut_token_in_use()` maps the refused token to the renewed one for the rest of the request: a press reads its token once and passes it to every call; without the map each later call would renew again and retire the token before it.
- A 401 is not processed by Paraşüt, so a POST sent again is not doubled.
- `parasut_me()` opts out (`$renew_401` false): at Connect its token is the code just exchanged, not the cached one.
- The 429 retry lives in `_parasut_request_once()`.

## Tests
- `tests/parasut_token_renewal_test.php`.
