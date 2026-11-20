---
name: decisions-made-product-89
description: Decisions list, product part 89 (2026-11-20 on): plans from the owner's hourly polls: a buybox repricer above a profit floor; NPS and CSAT surveys after a service, low scores followed up
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-product-88.md`.

## Should each marketplace account get its own price by rules and a repricer that watches the buybox, never under a profit floor? (owner, 2026-11-20, agent channel poll 173, channel message 10469)
- "Evet, plana girsin": channel price rules, the profit floor, Trendyol buybox reads, the repricer's strategies and limits, the report, the log and a try-first mode.
- Not chosen: "Evet, önce yalnız kanal fiyat kuralları ve kâr tabanı" (channel price rules and the floor first), "Hayır, gerek yok".
- The plan: `plans/marketplace-repricer.md`.

## Should the site ask for a score after a booking, ticket, course, form, delivery or mail conversation and follow up low scores? (owner, 2026-11-20, agent channel poll 187, channel message 10725)
- "Evet, plana girsin": NPS, CSAT and effort surveys, the touchpoint triggers, the one-click answer, the fatigue limits, the follow-up of a low score, the review invitation, the report and the API.
- Not chosen: "Evet, önce yalnız tek tıkla puan ve kapalı döngü" (the survey, triggers, one-click answer and low-score alert first), "Hayır, gerek yok".
- The review invitation is never filtered by the score (Google's rule against review gating). The plan: `plans/feedback-loop.md`.

Related: docs/memory/workflow/decisions-made.md, docs/memory/plans/marketplace-repricer.md, docs/memory/plans/feedback-loop.md
