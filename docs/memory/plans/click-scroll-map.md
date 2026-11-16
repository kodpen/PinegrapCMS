---
name: click-scroll-map
description: Plan (owner 2026-11-16, agent channel message 5667 and polls 67 and 75): clicks on a page's links and buttons and how far visitors scroll, in Visitors and as a layer in the designer; steps CM1 to CM5
priority: 6
---

## What is asked?
- The owner's words and the layout of the set: `plans/website-features.md`. The proposal is agent channel message 5664.
- For a page: the share of its visitors who clicked each link and button, and how far down they came. In Visitors per page, and in the visual designer over the page itself.

## What is there today?
- Visits, conversions, the funnel, experiments with a click goal (`data-pg-goal`), and the real-user speed beacon: `pg_vitals.src.js`, one `sendBeacon` to `page_view.php` signed for the page and the day, 30 a minute per address, an hourly table, 400 days (`visitors/page-vitals-beacon.md`). Nothing counts clicks or scrolling.
- `data-pg-node` is printed only while the editor's SEO check draws a draft, never on a stored or served page (`designer/editor-audit-panel.md`); a node id repeats in a loop and in a shared header. So a node id cannot be the key, as message 5664 had it.
- The cookie notice keeps the visitor's answer per category in `pg_consent`; a category it does not name is unanswered (`platform/cookie-consent-notice.md`).

## What did the owner decide about visitor data? (2026-11-16, agent channel polls 67 and 75)
- Owner (agent channel poll 67, message 5753), asked how the switch starts on a new install: "bu konuyu mimara danışırmısın. o tasarımcımız ve kullanıcı verisi işleme ile ilgili fikrini sunsun olgunlaşsın."
- The architect's view is agent channel message 5774. Its principle: a total that knows nobody waits for no consent, and whatever remembers a visitor does; a click is a person's behaviour, so the rule is tied to how the counter is built (C4 to C9) and the switch starts off but is never hidden (C16, C17).
- Owner (agent channel poll 75, message 5794), asked whether to go on with that view: "Evet, Mimar'ın görüşüyle". The other choices were the same with a new install starting on, and counting only visitors who said yes to statistics.

## What is built?
- The numbered features: `click-scroll-map-features.md` (C1 to C15) and `click-scroll-map-features-2.md` (C16 to C23). Steps: `click-scroll-map-steps.md`. Claim as `[plan:click-scroll-map] <step> — <topic>`; the rules: `website-features-rules.md`.
- In short: the browser names what was clicked by the part of the page, the address and the label, so no design changes and classic pages are counted too. One beacon a page view; counters per page, day and device; a section in Visitors and a layer in the designer.

## What did the architect decide?
- Architect (2026-11-16, agent channel messages 5763 and 5774): the four-part name and the bounds hold (200 elements, 60 characters, 400 days); with the speed measurement on, both go in one beacon. Still its own: the table's columns, in CM1's draft PR.

## What is left out?
- A heat map by coordinates and recorded sessions are outside the principle above: they need an owner decision of their own, which this one does not cover.
- Clicks on anything but links and buttons, a split by experiment variant, pages behind a sign-in. Each is a later yes.

## Whose look?
- The Visitors section with its empty state (CM3) and the designer's layer (CM4) are new: the owner's. The Features card is the architect's.
