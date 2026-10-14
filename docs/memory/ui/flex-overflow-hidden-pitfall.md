---
name: flex-overflow-hidden-pitfall
description: CSS pitfall - any overflow value other than visible zeroes a flex item's automatic minimum size (min-height: auto), collapsing panels on narrow screens.
---

## Why can overflow on a flex box collapse it?
- Every `overflow` value except `visible` turns the flex item's automatic minimum size (`min-height: auto`) to zero, so the box can be squeezed below its content.
- In the sales map panel this shrank the **panel to 0 px on a narrow screen and drew the province list over the map**. No sign on wide screens, because there `.pg-split` already gives itself `overflow-y: auto`. It shows only when the card drops to one column.

## What is the rule?
- Before writing `overflow` on a flex box, make sure the axis has a floor (`min-height` / `min-width`) - or, if a rule that sizes the box already exists, do not write `overflow`.
