---
name: erp-edocument-leftovers
description: Open ERP work (e-document phase 3): Paraşüt send path, queue worker, İşbaşı live key, code lists, e-waybill, accountant sign-off; owner works on it locally
---

## What is left of the ERP plan?
ERP phases -1 to 9 are done (2026.4.4–2026.4.10). Open, all e-document (phase 3) or outside approval:
1. Paraşüt driver: its send path ran live on the owner's demo company from 2026-10-19; what waits for a new demo key or a company with an e-document integration: `erp-parasut-open-work.md`.

2. Worker for `erp_edoc_queue` (the table exists, nothing processes it) and the e-document error notification that hangs on it: `erp-parasut-open-work.md`.

3. Logo İşbaşı live key (the owner enters it). The chain works end to end on the test environment.

4. Code lists from Logo: return and exemption codes, `eArchivePaymentType` for internet sales. GİB UBL-TR code lists v1.43 took effect 2026-09-14: review exemption and withholding codes.

5. e-waybill sending, only if the provider offers a "convert to e-document" call; until then waybills stay internal (`erp/erp-not-built-on-purpose.md`).

6. Accountant sign-off: VUK 509 fields, the return paths, the legal note (`erp/erp-edoc-cancel-paths.md`, which also holds the phase's acceptance list). Questions: `docs/erp_malimusavir_sorulacaklar.md`.

7. Manual checks on dev (owner): phase 6 tools through the Claude connector, purchase-order PDF and supplier e-mail, My Account → My Invoices in a template and the cart for a customer with account prices.

8. Removing the invoice and shipment code from `includes/fn/parasut.php` once no store relies on it (`erp/erp-parasut-legacy-order-buttons.md`); `view_parasut_inbox.php` keeps working.

## Who works on it?
- The Paraşüt driver (`includes/erp/edoc/parasut.php`): the owner assigned it to a cloud thread on 2026-10-19, lifting the 2026-10-13 rule for that file; what is left of it now runs in the owner's local session (`erp-parasut-open-work.md`). The rest of `includes/erp/edoc/` stays the owner's local work.
- Driver contract: comment at the top of `includes/erp/edoc/registry.php`; layer: `erp/erp-edoc-driver-layer.md`. Provider comparison: project note `notes/efatura-saglayici-karsilastirma.md`.
