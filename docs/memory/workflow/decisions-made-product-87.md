---
name: decisions-made-product-87
description: Decisions list, product part 87 (2026-11-20 on): server-side events to Meta, GA4 and TikTok enter a plan; personal data only with consent, not by default with the cookie notice off
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-product-86.md`.

## Are purchases and other events sent from the server to the ad platforms? (owner, 2026-11-20, agent channel poll 166, channel message 10193)
- "Evet, plana girsin": Meta's Conversions API, the GA4 Measurement Protocol and TikTok's Events API; six events (purchase, refund, add to cart, begin checkout, sign-up, lead); a queue and a log on the panel; the shop pastes its own keys, with no Meta app review and no middle server.
- Personal data (hashed e-mail and phone, browser and click ids) goes only for a buyer who accepted marketing cookies; marketplace orders are never sent.
- Not chosen: "Evet, önce yalnız Meta ve satın alma" (Meta and the purchase event first), "Hayır, gerek yok".
- The plan: `plans/ad-conversions.md`.

## Does personal data go to the ad platforms while the cookie notice is off? (owner, 2026-11-20, agent channel poll 175, message 10513)
- "Varsayılan gitmesin, mağaza açabilsin": with the notice off nobody is asked, so no personal data goes by default; each service's Connections page has "Send personal data while the cookie notice is off", which the shop ticks at its own risk; off by default, per service, it acts only while the notice is off, and ticking or clearing it writes an activity log line (architect, message 10516).
- Why asked: poll 166 says "a buyer who accepted"; `decisions-made-customers.md` (owner, 2026-10-28) counts marketing as the software's feature, and `pg_consent_allows()` answers yes while the notice is off.
- Not chosen: "Hiç gitmesin" (never), "Hep gitsin" (always, no box).

Related: docs/memory/workflow/decisions-made.md, docs/memory/plans/ad-conversions.md, docs/memory/platform/cookie-consent-notice.md
