---
name: decisions-made-product-81
description: Decisions list, product part 81 (2026-11-20 on): every order's costs and net profit, by product and channel, from the services' real deductions where they give them
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-product-80.md`.

## Does the store show each order's real net profit? (owner, 2026-11-20, agent channel poll 164, channel message 10092)
- "Evet, plana girsin": each order's costs (product cost, payment fee, marketplace commission and deductions, shipping) are read from the services' real figures where they give them (iyzico, Stripe, Trendyol finance, carrier labels), else from typed rates; net profit and margin on the order with a "sold at a loss" filter; product by channel by period; a break-even price; a bell; API and MCP.
- Prices never change by themselves.
- Not chosen: "Evet, önce yalnız yazılan oranlarla" (typed rates only at first, real deductions later), "Hayır, gerek yok" (the period profit and loss report is enough).
- The plan: `plans/order-profit.md`.
