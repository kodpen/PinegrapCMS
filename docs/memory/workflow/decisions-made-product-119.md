---
name: decisions-made-product-119
description: Decisions made, product part 119 (2026-11-27 on): US sales tax is not built for now; automatic tax stays the EU's rates and the one-stop-shop report
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-product-118.md`.

## Is US sales tax built? (owner, 2026-11-27, agent channel poll 249, message 14895)
- Asked after the count of automatic tax: in the US the rate changes by state, county and product and no free, dependable source of rates exists, so a shop would connect a tax service with its own account and key on the Connections screen, as with a payment provider; every such service costs the shop money. The question was which service to build first, with Stripe Tax recommended.
- Owner: "ABD şimdilik yapılmasın". Not chosen: any of the services.
- So: no tax driver, no connection to a tax service and no US rate is built. Automatic tax is the EU's rates by the buyer's country, the reduced rate and the one-stop-shop report (`plans/tax-automatic.md`, then `commerce/`).
- A shop selling to the US sets its rates by hand in its tax zones, as today.
- It comes back only when he asks for it; no session proposes a tax service or adds one to a plan.
