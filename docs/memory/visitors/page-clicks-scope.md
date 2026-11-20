---
name: page-clicks-scope
description: What the clicks and scroll depth counter is for and where it stops: the owner's and the architect's lines of 2026-11-16 (polls 67 and 75, messages 5763, 5774), the bounds, what is left out, whose look
module: page_clicks
---

## What was asked?
- Owner (2026-11-16, agent channel message 5667, the website-features set): for a page, the share of its visitors who clicked each link and button and how far down they came, in Visitors per page and over the page in the visual designer. The proposal was message 5664.
- Built in #9902 (counter), #9946 (script and Features card), #9913 (Visitors section, report route), #10056 and #10715 (designer layer).

## Why no consent is asked?
- Owner (2026-11-16, polls 67 and 75, messages 5753 and 5794): "Evet, Mimar'ın görüşüyle" (`workflow/decisions-made-product-60.md`).
- Architect (2026-11-16, agent channel message 5774): a total that knows nobody waits for no consent; whatever remembers a visitor does. A click is a person's behaviour, so the rule holds only while the counter keeps no cookie, identifier, visitor address or row per person, and the switch starts off and is never hidden.
- The check itself: `platform/consent-anonymous-counts.md`.

## Which bounds hold?
- Architect (2026-11-16, agent channel messages 5763 and 5774): an element is named by four parts (page part, link or button, address, label), at most 200 elements a page and day, labels cut at 60 characters, rows kept 400 days; with the speed measurement on, both travel in one beacon.
- A node id (`data-pg-node`) is never the key: it is printed only while the editor's SEO check draws a draft and repeats in loops and shared headers.

## What is left out?
- A heat map by coordinates and recorded sessions: outside the 5774 principle, they need an owner decision of their own.
- Clicks on anything but links and buttons, a split by experiment variant, pages behind a sign-in: each a later yes, not built.

## Whose look?
- The Visitors section with its empty state and the designer's "Clicks" layer are the owner's look; the Features card "Clicks and scrolling" is the architect's.

## Where is the rest?
- Counter `page-clicks.md`, switch `page-clicks-switch.md`, script `page-clicks-script.md`, designer `page-clicks-designer-layer.md`, Visitors `ui/visitors-clicks-section.md`.
