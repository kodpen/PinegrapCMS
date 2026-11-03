---
name: panel-colour-tokens
description: B10 colours (#4836): panel surfaces on Bootstrap 6's gray scale (dark one step lighter), text and lines Bootstrap 6's own, the v5 accent names dropped, plain fills kept for the charts
---

## Where do the neutrals and accents come from (B10 colours, #4836)?
- Surfaces: the bridge at the end of backend.src.css sets `--bs-bg-1` / `--bs-bg-2` from Bootstrap 6's gray scale, `light-dark(gray-025, gray-900)` and `light-dark(gray-050, gray-800)`. Dark sits one step lighter than Bootstrap's own because the panel's dark body (#101114) is lighter than Bootstrap's gray-975; with Bootstrap's steps the surfaces vanished into it.
- Text and lines (`--bs-fg-body`, `-fg-2`, `-fg-3`, `--bs-border-color`, `-translucent`) are Bootstrap 6's own: no override. Muted text got darker in light (fg-3 about 6:1 on white, was 3:1 at v5's 50% alpha) and borders a step stronger.
- The navbar separator is the one scoped border colour, on gray-300 / gray-700. `tests/bs6_colour_tokens_test.php` refuses a v5 grey literal in a neutral.
- The first `:root` block keeps only the plain fills `--bs-primary` … `--bs-info`: the charts of `view_orders.php` and `erp_profit.php` read them. The v5 `-rgb`, `-text-emphasis`, `-border-subtle` names and the duplicate link colours went; Bootstrap 6 reads none of them.
- The body (`--pg-body-bg-rgb`), card fill and the Material pastel palette did not change.
