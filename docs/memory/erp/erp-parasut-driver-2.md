---
name: erp-parasut-driver-2
description: Paraşüt driver, part 2: how the conversion picks e-Fatura or e-Arşiv (fresh GİB lookup, the card's alias, the scenario, a failed lookup) and its request layer (token lock, retries, throttle)
---

## How does the conversion pick e-Fatura or e-Arşiv?
- At conversion time, never from a cached answer: `erp_edoc_parasut_decide_kind()` sends a final consumer or a buyer without a number to e-Arşiv; any other number, a TCKN too, gets a fresh `e_invoice_inboxes` lookup. No mailbox: e-Arşiv; else e-Fatura to the first mailbox, or to the alias the account card holds while GİB still lists it.
- The e-Fatura scenario comes from `erp_invoices.edoc_scenario` (`commercial`, else `basic`).
- A failed lookup refuses the conversion with its reason; the row stays `created` (a draft at Paraşüt) and is handed over again later.
- Requests and the rest of the path: `erp-parasut-driver.md`.

## How are its requests made?
- `erp_edoc_parasut_request()` on `erp_edoc_http()` and `erp_edoc_log()`, with the legacy client's token (`parasut_get_token()`, `erp-parasut-connection.md`). A renewal runs under a named `GET_LOCK` per company and client; whoever waited reads the token the other one stored.
- One retry after a 401 (a new token) and one after a 429 (the next window); `parasut_throttle()` keeps 10 requests per 10 s per process.
