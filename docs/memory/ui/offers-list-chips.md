---
name: offers-list-chips
description: Offers list (view_offers.php) Flow chips and status filter chips: gift only when free, both usage limits, optional chips drawn hidden and shown by recount(), the offer-only 'Shipping {var:1}' key
---

## What does a Flow chip say?
- `_pg_offer_action_chip()` follows `_pg_offer_action_text()`: an added product reads "gift" only at 100% off, "full price" at 0, otherwise the discount (`−₺5,25`, `%50`). Before, every added product read "hediye" (#5509).
- A usage limit with both limits shows both: "En çok 100 kullanım · Müşteri başına 2×" (#5509).
- Shipping uses its own `'Shipping {var:1}'` key ("Kargo %100"), as the editor says "Kargo"; the shared `'Shipping'` is "Nakliye" (#5513).

## How do the status filter chips keep their counts?
- Scheduled, Expired and Incomplete may be empty: they are always drawn, `d-none` at 0, with `data-optional="1"`. `recount()` after a toggle writes every count and shows or hides each optional chip; the active chip is never hidden. Before, a chip absent at load never appeared, so an offer toggled into Planned sat in no chip (#5512).

## Which Turkish texts read as conditions?
- The summary lists conditions in the "-sa" form: `cart has at least {var:1} from {var:2}` is "… ürün varsa", `at most {var:1} time(s) per customer` is "… kez kullanılmışsa"; the shared `Expired` is "Süresi doldu" (sentence case, like "Devre dışı") (#5513).
