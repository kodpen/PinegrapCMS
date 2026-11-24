---
name: figma-api-rate-limits
description: Figma REST rate limits since 2025-11-17 (read 2026-11-24): three tiers, per seat and plan; View and Collab seats get up to 20 file or image reads a month; 429 with Retry-After
---

## How are the limits set?
- By the person's seat, the endpoint's tier and the plan of the file asked for; leaky bucket. In force since 2025-11-17; Figma may change them.
- OAuth app: counted per person, per plan, per app. Personal access token: per person who made it, per plan. Plan access token: per token, per plan.

## Which calls sit in which tier?
- Tier 1: GET file, GET file nodes, GET images.
- Tier 2: image fills, variables (GET), comments, webhooks, versions, team and folder lists.
- Tier 3: components and styles, file metadata, users.

## What are the numbers?
- Tier 1: Dev or Full seat 10/min (Starter), 15/min (Professional), 20/min (Organization); View or Collab seat up to 20 a month on every plan.
- Tier 2: Dev/Full 25, 50, 100 per minute; View/Collab up to 5 per minute.
- Tier 3: Dev/Full 50, 100, 150 per minute; View/Collab up to 10 per minute.
- The page leaves Enterprise Dev/Full blank. View and Collab limits may be lower under load.

## What does a 429 carry?
- `Retry-After` (seconds), `X-Figma-Plan-Tier` (`enterprise`, `org`, `pro`, `starter`, `student`), `X-Figma-Rate-Limit-Type` (`low` for View/Collab, `high` for Full/Dev), `X-Figma-Upgrade-Link`.

## What does it mean for an import?
- One import should cost few Tier 1 calls: GET file with `depth=2` for the frame list, one GET images for all thumbnails, one GET file nodes for the ticked frames, one GET images for vectors.
- A person on a View or Collab seat has about 20 such calls a month; the window should show the 429's reason and wait time rather than retry.
