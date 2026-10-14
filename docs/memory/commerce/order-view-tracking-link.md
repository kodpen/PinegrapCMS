---
name: order-view-tracking-link
description: Order view tracking tokens and flag, how the carrier provider is resolved, supported provider keys with URL templates, and how to add a provider.
---

## Which tokens and flag exist?
- `__tracking_code`: raw tracking number (`orders.tracking_code`).
- `__tracking_link`: carrier deep-link URL (empty if the provider cannot be resolved).
- `__tracking_company_name`: carrier display name (e.g. "Yurtiçi Kargo").
- Visibility flag `has_tracking_link`: true when both code and provider resolve.

## Where does the provider come from?
1. `orders.tracking_company` column (optional; defensive `SHOW COLUMNS` probe).
2. Otherwise the `ECOMMERCE_DEFAULT_TRACKING_PROVIDER` config define.

## Supported provider keys and URL templates
| Key | Carrier | URL template |
|---|---|---|
| `yurtici` | Yurtiçi Kargo | `https://www.yurticikargo.com/tr/online-servisler/gonderi-sorgula?code={code}` |
| `aras` | Aras Kargo | `https://kargotakip.araskargo.com.tr/?code={code}` |
| `mng` | MNG Kargo | `https://service.mngkargo.com.tr/iettransport/iettransport.svc/json/GetMngShipmentStatus?Code={code}` |
| `ptt` | PTT Kargo | `https://gonderitakip.ptt.gov.tr/Track/summary?id={code}` |
| `surat` | Sürat Kargo | `https://www.suratkargo.com.tr/KargoTakip/?kargotakipno={code}` |

## How do I add a provider?
- Add it to BOTH `_eo_tracking_provider_url()` and `_eo_tracking_provider_label()` (`functions.php`). Use the literal `{code}` sentinel in the URL template.
