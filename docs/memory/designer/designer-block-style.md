---
name: designer-block-style
description: Where CSS for ready-made designer blocks may live: Bootstrap classes, _SD_BLOCK_CSS in the Styles panel, never inline style or a <style> element.
---

## Where may block CSS live?
Extra CSS either does not exist or is defined in the design's Styles panel. Inline `style` and a page-printed `<style>` element are both invisible to the operator.
1. **If Bootstrap has it, use the class; write no rule:** `object-fit-cover`, `display-*`, `fs-*`, `overflow-x-hidden`, `opacity-*`, `position-sticky` (Bootstrap 5.3.8, `assets/lib/bootstrap-5.3.8`).
2. **Otherwise put the rule in `_SD_BLOCK_CSS` and the need in `_SD_BLOCK_CSS_NEEDS`.** When a block is dropped, `_sdEnsureBlockStyles()` adds only that block's rules to User Styles; if the name already exists it does not touch it, so an operator-edited rule is kept and a second drop does not duplicate. Class names take the `pg-` prefix.
3. **No `<style>` element is printed into the page.** A stylesheet in a `custom_html` node is a rule that never enters the undo stack and is invisible in the panel.

## What is the one exception?
- Style properties written by the properties panel itself: `background-image` / `-size` / `-position` / `-repeat` / `-attachment`, `background-color`, `font-size`, `object-position` (`setAttrStyleProp`). There the `style` attribute is the panel's **store**; moving it to a stylesheet takes the control away from the operator.
- Also inline: the `progress-bar` width Bootstrap writes as data.
