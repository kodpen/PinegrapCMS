---
name: carrier-label-aras
description: Aras Kargo label driver (2026.11.0, #3259), the first direct Turkish carrier: SOAP from its WSDL, integration code as reference, Aras's own ZPL/PDF label, cancel, waybill status, unverified points
---

## What is the driver?
- `includes/shipping/aras.php`, key `aras` in `pg_label_builtin_drivers()`. Built from the service's own published description: the WSDL of `arascargoservice.asmx` (live `customerws.araskargo.com.tr`, test `customerservicestest.araskargo.com.tr/arascargoservice/`) and its imported `?schema=IrsaliyeData`. No prose reference is public: meanings beyond names and types are unverified. Contract: `carrier-label-drivers.md`.
- Not Yurtiçi first (#3073: open reference and test mode first): its documents come from the sales representative after approval, its services answer a registered IP only.
- SOAP 1.1 by hand, elements in the WSDL's order (no ext-soap); answers read with DOMDocument (`pg_label_aras_parse()`: no DOCTYPE, LIBXML_NONET; every name a list; a DataSet's inline schema skipped). Without DOM nothing is sent.
- Fields: `username`, `password` (secret), `customer_code` (tracking), `mode` (test default), `sender_address` (SenderAccountAddressId; empty = default), `label_format` (pdf, zpl).

## What do the operations call?
- create: refuses a return, an address abroad, and one without name, address, district (`city`), province (`state`) or a 10-digit phone. Reference = integration code: the order number's last ten digits + six random ones. `SetOrder`: one piece (BarcodeNumber = code), kg and desi (cm³/3000) rounded up to whole numbers, PayorTypeCode 1, IsCod 0, InvoiceNumber and TradingWaybillNumber = order number; ResultCode "0" is success. Then `GetArasBarcode`: number = TrackingNumber, else Barcode; label = the ZebraZpl strings or the first ZebraPdf (base64). No number: `CancelDispatch` at once (plain failure; unclear, naming the code, when that fails too). A number without a readable label succeeds; label() asks again.
- label: `GetArasBarcode`. cancel: `CancelDispatch`, ResultCode "0". test: `GetAddressList`; no address, or none of `sender_address`, fails; account = its branch (`Sube`).
- track: `GetCargoInfo` (needs `customer_code`), the latest `IrsData` row: a return reason in words (not YOK, -) or "iade" in `DURUMU` returned; "teslim edildi", or `TESLIM_TARIHI` with `TESLIM_ALAN`, delivered at that date and hour (Turkish time); "dağıtımda" out_for_delivery; a waybill or `KARGO_TAKIP_NO` in_transit at the waybill date. The link is built from `KARGO_TAKIP_NO`. `DURUM_KODU` values are unpublished: logged only.
- No pickup: `Recall` has no date or window (`shipment-label-pickup-drivers.md`).

## Which failures are unclear?
- `SetOrder` and `CancelDispatch` sent without an answer, answered by a 5xx or 200 that is no SOAP envelope, or by an envelope without a result. A SOAP fault (HTTP 500) is Aras's own refusal, plain; so is an unreadable 4xx.

## What is unverified?
- No call was made: no credentials, and the test user comes from Aras. Unverified: result codes and messages; whether GetArasBarcode answers right after SetOrder, and only for accounts set up for sender-printed labels (an order-based account, whose branch prints the label, gets none and is cancelled); the ZebraPdf encoding; GetAddressList as a credential check; GetCargoInfo's user and status words; PayorTypeCode. Tests: `tests/carrier_label_aras_test.php` with `pg_label_aras_transport()` and `tests/fixtures/aras/`.
