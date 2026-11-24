---
name: main-menu-widening
description: The main menu goes between rail, open and hidden and opens a panel without a stutter: no animated grid column (.pg-menu-grows), rows at the open width, icon moves held
---

## Why does the menu widen without a stutter?
- The grid column (`grid-template-columns` of `.software-container`) is never animated: an animated column laid the whole page out again on every frame, which was the stutter the owner saw (#11271). Only the fixed rail slides (width, transform).
- `paintMenuState()` sets `.pg-menu-grows` when the column grows (hidden → mini → open): the column then takes its width at once after the 0.3 s slide (`transition: grid-template-columns 0s linear 0.3s`), the rail sliding over the content as a peek does. A shrinking column changes at once, under the rail still narrowing.
- Set to open, the rows get the expanded width at once (`#menu.expanded .pg-menu-main` min-width), as the panels have theirs, so the labels are not ellipsised again on every frame.
- While the rail's width or slide runs, `initMenuMotion()` puts `.pg-menu-moving` on `#menu` (never the container, see below) (transitionrun to transitionend/cancel) and the icons' hover moves do not start. Reduced motion: no transition at all.

## What must a change keep?
- A new class toggled on `.software-container` restyles the whole page (about 50 ms on a 4,000-element screen, `css-class-attribute-subtree-restyle.md`): put a class that only the menu needs on `#menu`; a container class goes in the same task as the state change.
- Measured on the sandbox dashboard at 1440 px, mini to open: layout 77 ms to 18 ms, script 133 ms to 21 ms over the slide (the dashboard's widgets no longer resize on every frame).
- No transition on `grid-template-columns` but `.pg-menu-grows`' delayed one; anything that animates the column brings the stutter back (`tests/menu_state_test.php`).

Related: docs/memory/ui/main-menu-states.md, docs/memory/ui/main-menu-panels.md, docs/memory/ui/main-menu-icon-motion.md
