---
name: shipping-panel-terms
description: The Turkish words the shipping screens use for fees, handling and transit days, cut-offs and adjustment direction, and which shared keys carry them (#5556, #5557, #5567)
---

## What is a shipping "rate" in Turkish?
- A fee in money, so `Ücret`, never `Oran` (which reads as a percentage): Base Rate `Temel Ücret`, Primary/Secondary Weight Rate `Birincil/İkincil Ağırlık Ücreti` (list `… A. Ücreti`), Item Rate `Ürün Başına Ücret`, Real-Time Rate `Gerçek Zamanlı Ücret`. The keys are shared by the shipping method and shipping zone screens and lists, so both read the same.

## Do the list headings match the form?
- Yes: the short list keys carry the form's names. Handling = Handling Time = `Hazırlama Süresi` (not `Ambalajlama`, packing), Transit = Base Transit Time = `Teslimat Süresi`, Street = `Sokak Adresi`. Those short keys are used only by `view_shipping_methods.php`.

## What is a cut-off called?
- `Son kargo saati` everywhere: Shipping Cut-offs `Son Kargo Saatleri`, Add Shipping Cut-off, Cut-off Date & Time, the method form's Cut-off time and the arrival date notices. `Kesinti` (a deduction or an outage) and `Saat Limiti` are gone.

## How does an adjustment's direction read?
- `erken` / `geç` (`{var:1} gün erken`), not `önce` / `sonra`: the list's Created column says `18 gün önce` for a relative time beside it. The delete confirmation says `gönderim tarihi ayarlaması`, the screen's name.

## And the zone screens?
- States = `Eyaletler/İller` (it was `Şehirler`, confusable with the address city), and the shipping zone card heading is written like the tax zone one: `… Bölgesi Eyaletleri/İlleri`.
