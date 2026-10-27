---
name: decisions-made-panel
description: Part of the decisions list: the control panel's Bootstrap 6 breakpoints (own media queries on 1024/1280, xxl to 2xl), kept as they are on purpose
---

## Why do the panel's own media queries break at 1024 and 1280 px?
- Owner, 2026-10-27, at the Bootstrap 6 switch (#1866), answering whether they should stay on 992/1200: "önerilerini destekliyorum". Bootstrap 6 moved `lg` to 1024 and `xl` to 1280 px; the panel's own queries moved with them, so they change at the same width as the `lg:`/`xl:` classes on the same screen. Between 992 and 1023 px the menu and layouts show one step smaller than under Bootstrap 5. Not a wrong breakpoint.

## Why do the product and user screens use the narrower layout at 1440 px?
- Owner, same day and words: Bootstrap 5's `xxl` (1400 px) classes follow the official rule to `2xl` (1536 px), not to the panel's `xl` (1280 px). From 1400 to 1535 px those screens use the `xl` column split (the product form a little narrower, the preview column wider). Not a lost breakpoint; do not map `2xl` back to `xl`.
- The official v5 to v6 rules stay the codemod's rules (`ui/bootstrap-6-codemod.md`); a visual difference they bring is fixed forward, not by a second mapping.
