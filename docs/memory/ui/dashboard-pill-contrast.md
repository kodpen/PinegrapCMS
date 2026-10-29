---
name: dashboard-pill-contrast
description: Dashboard cards: tinted pills mixed 50% toward the text colour, zero-count and expired rows on .fg-2 instead of opacity, the headline arrow's direction in words
---

## How is a pill on a tint of its own tone made readable?
- Ecommerce Summary periods (`widget_3.php`), Online Engagement roles (`widget_4.php`) and Subscriptions days left (`widget_14.php`) print `background:<hex>22` and `color:color-mix(in srgb, <hex> 50%, var(--pg-fg-emphasis))`.
- The bare tone on its 13% tint was 1.7–3.9:1; the 50% mix reaches at least 5.6:1 for every tone on every card in both themes. 80% and 60% leave amber and green under 4.5:1.
- No card fill, accent or `--pg-*` token changed (token tuning is step B10).

## How are secondary rows drawn?
- `pg_widget_row()`'s `muted` (Ecommerce Summary's zero-count periods) and the expired Subscriptions row take `.fg-2`, not `opacity-50` (about 3.0:1 in light). `.fg-2` beats `.pg-row`'s `color: inherit` because utilities sit in a later layer than `@layer custom`.
- The Comments card's location line (`widget_25.php`) is plain `fg-secondary`; opacity .75 had it at 3.8:1.

## How does the headline say its direction?
- `pg_widget_headline()` marks its up/down/flat arrow `aria-hidden` and prints a `visually-hidden` "Up from yesterday", "Down from yesterday" or "Unchanged from yesterday"; the hover title alone never reached a screen reader.

## What keeps it?
- `tests/dashboard_pill_contrast_test.php` checks the markup, computes the ratios and renders the headline.

Related: docs/memory/ui/panel-content-contrast.md
