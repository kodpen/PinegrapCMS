---
name: designer-block-style
description: Where CSS for ready-made designer blocks may live: Bootstrap classes, _SD_BLOCK_CSS in the Styles panel, never inline style or a <style> element; how a design template ships them.
---

## Where may block CSS live?
Extra CSS either does not exist or is defined in the design's Styles panel. Inline `style` and a page-printed `<style>` element are both invisible to the operator.
1. **If Bootstrap has it, use the class; write no rule:** `object-fit-cover`, `display-*`, `fs-*`, `overflow-x-hidden`, `opacity-*`, `position-sticky` (Bootstrap 5.3.8, `assets/lib/bootstrap-5.3.8`).
2. **Otherwise put the rule in `_SD_BLOCK_CSS` and the need in `_SD_BLOCK_CSS_NEEDS`, without `!important`.** When a block is dropped, `_sdEnsureBlockStyles()` adds only that block's rules to User Styles; if the name already exists it does not touch it, so an operator-edited rule is kept and a second drop does not duplicate. Class names take the `pg-` prefix.
3. **No `<style>` element is printed into the page.** A stylesheet in a `custom_html` node is a rule that never enters the undo stack and is invisible in the panel.

## How is a block rule written?
- One `_SD_BLOCK_CSS` entry may hold several rules, media queries and keyframes, but its key must appear in it as a class (`'pg-tilt'` holds `.pg-tilt`): `_sdEnsureBlockStyles()` decides "already there" by that class, so a key that is not a class of its rule is appended again on every drop (`tests/designer_blocks_test.php` checks it).
- Descendant selectors only: the canvas wraps every node in `.sd-wrap`, so `>`, `+`, `~` between two tree nodes never match there.
- Bootstrap utilities are `!important`: an element whose property a rule changes (display, overflow) carries no utility for that property.
- Colours in a rule are `var(--bs-…)`, `var(--pg-…)` or `currentColor`; motion stops under `prefers-reduced-motion`. Shared entries: `pg-marquee`, `pg-shade-bottom`, `pg-pulse-still` (`designer-block-motion.md`, `designer-block-theme-colours.md`).

## What is the one exception?
- Style properties written by the properties panel itself: `background-image` / `-size` / `-position` / `-repeat` / `-attachment`, `background-color`, `font-size`, `object-position` (`setAttrStyleProp`). There the `style` attribute is the panel's **store**; moving it to a stylesheet takes the control away from the operator.
- Also inline: the `progress-bar` width Bootstrap writes as data.

## How does a design template ship block CSS?
- In its `assets` 'css': `pg_design_template_user_styles(array('news-ticker', 'pg-shade-bottom'), $own_css)` (`includes/fn/designer.php`) returns the one User Styles entry (`meta: user-styles`). Names are block types (their `_SD_BLOCK_CSS_NEEDS`) or rule names.
- `pg_designer_block_css()` reads `_SD_BLOCK_CSS` out of `style_designer.js` (no PHP copy) and writes the text byte for byte as a drop does, so a later drop finds the rules and adds only missing ones (`tests/design_template_assets_test.php`).
- Not a separate `css` entry: the drop checks only User Styles, and a named `.css` entry is also written to the file manager. `$own_css` never names a block rule's class.
