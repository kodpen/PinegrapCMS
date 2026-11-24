---
name: css-class-attribute-subtree-restyle
description: A [class] compound followed by a descendant part (`.row > [class] > …`) makes any class change on any element restyle its whole subtree in Chromium; where backend.src.css has it, why it is still there
---

## What is the trap?
- A selector with `[class]` in a compound that is not the subject, followed by `>` or a space (`.pg-set-card .row > [class] > :is(…)`), gives Chromium an invalidation set on the `class` attribute that invalidates all descendants.
- Then a change of any class on any element restyles that element's whole subtree, even a class no rule names. On `.software-container` that is the whole page: about 50-60 ms on view_pages (4,300 elements), measured 2026-11-24 with the invalidation trace (`StyleInvalidatorInvalidationTracking`, "allDescendantsMightBeInvalid").

## Where is it?
- `backend.src.css`, the settings card "one setting to a line" block (`@supports selector(:has(*))`): `.pg-set-card .row > [class] > :is(.form-label, …)`, `.pg-set-card .row > [class] > .form-field > :is(…)`, `.pg-set-card .row > [class] > .form-field:has(…) ~ .form-field > *`.
- Not changed in #11271 (the main menu step): `[class]` carries weight (0,1,0) the placements count on (`css-has-whole-document-restyle.md`, `settings-card-layout-pitfalls.md`), and a replacement needs the 14 settings panes compared old against new.

## What do I do meanwhile?
- Toggle as few classes as you can on high ancestors (`html`, `body`, `.software-container`); several in one task cost one restyle. The main menu puts its own classes on `#menu` (`main-menu-widening.md`).

Related: docs/memory/ui/css-has-whole-document-restyle.md, docs/memory/ui/main-menu-widening.md
