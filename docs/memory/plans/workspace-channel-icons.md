---
name: workspace-channel-icons
description: Plan (2026-10-23, slices 1-3 done #1510, answers given): left: channel templates carry icon and colour, optional group icons
---

## What is asked? (owner, 2026-10-23)
- "Assign an icon to channels."

## What is built?
- Slices 1-3 (#1510, schema 11.0.81): a chosen icon from a curated list, the kind kept as a corner badge, the picker, `icon` and `color` on the API: workspace/workspace-channel-icon.md.

## What is left?
- Templates: a `ws_templates` body may carry icon and colour, applied by `ws_template_apply()` (no schema).
- Optional: icons for `ws_channel_groups`, only if asked.

## Where to start?
- The template part; read workspace/workspace-channel-icon.md. If the owner drops it, delete this plan.
