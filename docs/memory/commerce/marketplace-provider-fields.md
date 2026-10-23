---
name: marketplace-provider-fields
description: How a marketplace provider declares its credential fields in mp_providers() (text, secret, select), help, cargo template and barcode keys, and the transport seam of Trendyol's functions.
---

## How are a provider's credential fields declared?
- `mp_providers()[...]['fields']`: text box by default (`secret` → password); `'type' => 'select'` with `options` and `default` (Trendyol's live/test environment). `mp_provider_credentials()` reads a form and only ever stores one of a select's options; a select is never "required". Optional `help` is drawn under the fields; `shipment_template` shows the account's cargo template field; `key => 'barcode'` (`mp_provider_keys_by_barcode()`) makes the barcode the product's key. The new-account modal draws every provider's block and disables the hidden ones.
- Trendyol's functions take an optional `$transport` (signature of `api_http_request()`); `mp_call()` never passes it, tests pass a fixture recorder.

Related: docs/memory/commerce/marketplace-outbound-layer.md
