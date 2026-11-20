---
name: decisions-made-product-87
description: Decisions list, product part 87 (2026-11-20 on): purchases and other events sent from the server to Meta, GA4 and TikTok, personal data only with marketing consent, enter a plan
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-product-86.md`.

## Are purchases and other events sent from the server to the ad platforms? (owner, 2026-11-20, agent channel poll 166, channel message 10193)
- "Evet, plana girsin": Meta's Conversions API, the GA4 Measurement Protocol and TikTok's Events API; six events (purchase, refund, add to cart, begin checkout, sign-up, lead); a queue and a log on the panel; the shop pastes its own keys, with no Meta app review and no middle server.
- Personal data (hashed e-mail and phone, browser and click ids) goes only for a buyer who accepted marketing cookies; marketplace orders are never sent.
- Not chosen: "Evet, önce yalnız Meta ve satın alma" (Meta and the purchase event first), "Hayır, gerek yok".
- The plan: `plans/ad-conversions.md`.

Related: docs/memory/workflow/decisions-made.md, docs/memory/plans/ad-conversions.md, docs/memory/platform/cookie-consent-notice.md
