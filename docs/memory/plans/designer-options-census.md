---
name: designer-options-census
description: Plan (owner 2026-11-24, before the launch): more ways to shape an element in the designer's Options panel; DO1 counts what is missing, the owner picks, the architect writes the build steps
priority: 7
---

## What is asked?
- Owner (2026-11-24): "daha fazla özelleştirme seçenekleri kaliteli şekilde planlayalım" (`workflow/decisions-made-product-105.md`).

## Which steps?
- DO1 (S) Census, no product code, written to designer/options-panel-census.md: every control the Options panel has today per element kind, and against it what a designer cannot set without code.
- DO1 looks for at least: grid tracks and areas, position and layer order, per screen size values, hover, focus and pressed states, transitions and entrance motion, gradients and several backgrounds, filters and blend, transforms, text effects, masks and clip shapes, sticky and scroll snapping, aspect ratio, object fit. Each missing one with its size (S or M) and what it would read from the design's variables.
- After DO1 the architect brings the list to the owner; what he picks becomes DO2 onward here. No build step is given out before that.

## What does the census keep?
- It names no control as dead or unused: a proposal to remove something goes to the architect with the decisions list searched first.
- Claim as `[plan:designer-options-census] DO1 — census`.
