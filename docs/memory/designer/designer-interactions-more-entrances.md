---
name: designer-interactions-more-entrances
description: The four later entrances (Zoom out, Blur in, Rotate in, Reveal, DO18): start and hover states, what Distance means for each, Reveal entrance-only, filter and clip-path composed with the node's own
module: designer_fx
---

## Which entrances are there?
- Ten in the Effect list of Interactions: Fade in, Slide up/down/left/right, Scale, and since DO18 `zoom-out`, `blur-in`, `rotate-in`, `reveal`, with the same Trigger, Duration, Delay, Easing, Stagger children and Every time rows. The page writes them as `data-pg-fx` like the six; the head style hides them by trigger, so no new rule.

## What does each do?
- Zoom out: starts faded at `scale(1.1)`; hover/click goes to `scale(0.95)`. No distance (dropped by `pg_fx_normalize()` and its twin).
- Blur in: starts faded and `blur(Npx)`; hover/click blurs. The Distance field is shown as "Blur" (px): 8 on an entrance, 2 on hover when unset.
- Rotate in: starts faded at `rotate(-N deg)`; hover/click turns `N deg` the other way. Distance is shown as "Angle" (°): 8 / 3 when unset.
- Reveal: a clip that opens from the bottom (`clip-path: inset(0 0 100% 0)` to the node's own), no fade, no distance. Entrance only: the panel offers Scroll into view and Page load; a stored hover or click trigger is read as view by the normalizer, its twin and `pg_fx.src.js`.

## How does the script handle filter and clip-path?
- `shift()` adds `filter` (blur in) or `clip` (reveal) keys only for those effects, so the six keep their states and `transition()` strings byte for byte. `rest(o)` is the end state with the same keys emptied; `play()`, hover-off and `replay()` use it.
- `put()` composes a blur after the node's own inline filter; a clip stands in for the node's own clip-path while it moves. Both are kept in `pgFxBase` on first write and put back by `restore()`.
- The IntersectionObserver measures the unclipped box, so a clipped Reveal is seen like any entrance.

## How is it tested?
- `test_designer_fx_more_entrances`, the extra cases in `_test_fx_cases()` (twins and script read), `_more` in `test_designer_fx_script_reads_the_page`, panel rows in `test_designer_fx_panel`, blur and clip over the node's own in `test_designer_fx_script_on_the_canvas` (`tests/designer_fx_test.php`). Not run in a real browser.

Related: docs/memory/designer/designer-interactions.md, docs/memory/designer/designer-interactions-script.md
