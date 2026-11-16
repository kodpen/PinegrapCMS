---
name: click-scroll-map
description: Plan (owner 2026-11-16, message 5667), not to be started before the architect's view on visitor data the owner asked for (poll 67): clicks on a page's links and how far visitors scroll
priority: 6
---

## What is asked?
- The owner's words and the layout of the set: `plans/website-features.md`. The proposal is agent channel message 5664.
- For a page: the share of its visitors who clicked each link and button, and how far down they came. In Visitors per page, and in the visual designer over the page itself.

## What is there today?
- Visits, conversions, the funnel, experiments with a click goal (`data-pg-goal`), and the real-user speed beacon: `pg_vitals.src.js`, one `sendBeacon` to `page_view.php` signed for the page and the day, 30 a minute per address, an hourly table, 400 days (`visitors/page-vitals-beacon.md`). Nothing counts clicks or scrolling.
- `data-pg-node` is printed only while the editor's SEO check draws a draft, never on a stored or served page (`designer/editor-audit-panel.md`); a page's HTML is stored when it is saved; a node id repeats in a loop and in a shared header. So a node id cannot be the key, as message 5664 had it.

## What is built?
- The numbered features: `click-scroll-map-features.md` (C1 to C17). Steps: `click-scroll-map-steps.md`. Claim as `[plan:click-scroll-map] <step> — <topic>`; the rules: `website-features-rules.md`.
- In short: the browser names what was clicked by the part of the page, the address and the label, so no design changes and no page is saved again, and classic pages are counted too. One beacon a page view, built as the speed beacon is; counters per page, day and device; a section in Visitors and a layer in the designer.

## What binds it?
- `visitors/high-volume-counter-rules.md`: aggregate when writing, a sha1 key, free text bounded (C8), no `COUNT(*)`.
- `visitors/web-vitals-ai-visibility-decisions.md`: nothing per visitor, no cookie, no beacon for staff or in the designer.
- `ui/visitors-section-rules.md`: a section owns its file and never edits the screen, the shell or another section.

## What must mature first? (owner, 2026-11-16, agent channel poll 67)
- Asked whether a new install starts with the switch on (message 5753; the proposal was on, as the audits of `workflow/decisions-made-product-18.md`), he wrote instead: "bu konuyu mimara danışırmısın. o tasarımcımız ve kullanıcı verisi işleme ile ilgili fikrini sunsun olgunlaşsın."
- The architect was asked in agent channel message 5764: whether counting without consent holds, whether a label or an address can carry personal data, the default of a new install, what to add or drop. No step starts before its view has gone to the owner and he has decided; until then the description keeps "not to be started".

## What does the architect decide?
- The table's columns and indexes, in CM1's draft PR. The bounds: 200 elements, 60 characters, 20 elements a beacon.

## What is left out?
- A heat map by coordinates, recorded sessions, clicks on anything but links and buttons, a split by experiment variant, pages behind a sign-in. Each is a later yes.

## Whose look?
- The Visitors section (CM3) and the designer's layer (CM4) are new: the owner's, close-up pairs before either is marked ready. The Features card is the architect's.
