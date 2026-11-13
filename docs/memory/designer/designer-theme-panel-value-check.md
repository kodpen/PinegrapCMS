---
name: designer-theme-panel-value-check
description: What the Themes panel's text boxes write: one CSS value only (no ; { } < > comment, backslash or open bracket or quote), a colour row also a colour by CSS.supports; a refused box stays marked.
---

## What may a Themes panel text box write?
- One CSS value (`_sdThemeValueOk()`, `assets/js/style_designer_theme.js`): no `;`, `{`, `}`, `<`, `>`, backslash, control character, `/*` or `*/`, and every bracket and quote closed. A colour row (`kind` `color` or `colorRgba`) also asks `CSS.supports('color', value)`; `var(...)` passes it. An empty box always writes (it removes the variable).
- A refused value is not written: the box keeps the text, gets `is-invalid` and `aria-invalid`, and a toast says "Not written: … is not a colour." or "… is not a single CSS value.". The part's `write` entry refuses the same values (returns false).

## Why?
- The value goes into the variable's declaration in User Styles and, for a theme colour, into some twenty cascade rules (`_themeCascadeRulesFor()`). `red; } body { display:none } .x {` typed as Primary closed them, hid the whole page and broke every rule after it, while the box read back only `red` (#9000). A designer can write any CSS anyway; this guards against a typo.

Related: designer-theme-panel-light-selector.md
