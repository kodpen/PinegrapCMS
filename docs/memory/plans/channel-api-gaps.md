---
name: channel-api-gaps
description: Plan (owner 2026-11-15, agent channel message 5039): the API side of channels and the agent channel completed: both censuses done, steps CA2 to CA16 in channel-api-gaps-steps.md, then the close-out
priority: 6
---

## What is asked?
- Owner (2026-11-15, agent channel message 5039): "arayüz geliştirmelerinden önce ajan kanalları ve kanalllarla ilgili özellikleri öne alalım. api taraflarını tamamlayalım."
- The API was last compared with the panel on 2026-11-07 (#7967, `api/api-panel-coverage.md`). The agent channel and most of what channels gained since landed after it, each with the routes its own plan named.

## What did the two censuses find?
- CA1a, a channel's own side: done (#9764). 33 gaps: `api/channel-api-census-channel-gaps.md` and `api/channel-api-census-channel-gaps-content.md`; what has no route by its nature: `api/channel-api-census-channel-no-route.md`.
- CA1b, the agents' side: done (#9761). 7 gaps: `api/channel-api-census-agents-gaps.md`.
- What is not built on purpose, with the reason: `api/channel-api-not-built.md`.

## Which steps are there?
- CA2 to CA16, one per group of gaps, with who may call each: `channel-api-gaps-steps.md`.
- Last (S): close-out: the topic files, `api/api-panel-coverage.md` corrected, the census files folded into the topic files, this plan and its steps file deleted.

## What does every step keep?
- What has no route on purpose stays so: Site Settings and with it the Agents card (`api/api-settings-no-endpoint.md`), a write the person must make themselves (`workspace/workspace-api-callers.md`), a guest's reach (`api/channel-api-not-built.md`).
- A new route reuses the panel's function with its own checks (`api/api-panel-coverage.md`, "How is a write route for a panel screen added?"), has a dry run where it writes, its schema row and its tests.
- A route gives an application no more than its scopes and its channel membership give today. No new scope, table or column without the architect.
- Pace: at most three CA steps open at once on each coordinator's side, since they share the route table and the schema file. A step that clashes is brought up to date before it is marked ready, never after.
- Claim as `[plan:channel-api-gaps] <step> — <topic>`.
