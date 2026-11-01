---
name: dashboard-visitor-head-wrap
description: Visitor Summaries card head on a narrow card: the legend wraps below the figures, the figure lines keep to one line
---

## What was the symptom?
- At 375 px the "— Today - - Yesterday" legend kept its width beside the figures, the figure grid's second column fell to 28 px, the "visitors" unit ran over the legend and "all of yesterday 179" broke word by word (#4424, PR #4499).

## What does the card do now?
- `.pg-vs-head` (`assets/css/backend.src.css`) is `flex-wrap: wrap` with a 2 px row gap: when figures and legend do not fit one line the legend drops below. `.pg-vs-big` and `.pg-vs-sub` are `white-space: nowrap`, so the figure, its unit and the sub line never break. A wide card is unchanged.

## Where are the tests?
- `tests/dashboard_visitor_head_test.php` (reads the CSS rules).
