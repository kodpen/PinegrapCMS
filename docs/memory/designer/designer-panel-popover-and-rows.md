---
name: designer-panel-popover-and-rows
description: Bootstrap popover vs dropdown toggles with [title], and the options panel row()/sect()/as() layout rules in the designer.
---

## Why must a `[title]` toggle not get a popover?
- `backend.src.js` binds `$('[title]').popover()` to every element with a title. Bootstrap 5 keeps ONE component instance per element: if the popover binds first, a `data-bs-toggle="dropdown"` button's Dropdown instance cannot be created ("doesn't allow more than one instance per element"), `clearMenus` finds `getInstance` null and the menu never closes.
- The selector therefore excludes `dropdown / tab / pill / button / tooltip / popover` toggles. If a new toggle needs a hint, put the title on the wrapper, not the button.

## What are the options panel row and section rules?
- `row(label, ctrl)`: label column 92px and it WRAPS. Do not write `nowrap` (a long label went under the control).
- `sect()` (`.sd-props-section`) and `as()` (`.sd-app-sect`) use the same heading rule: an indented line under the heading. Do not give the section wrapper its own border: two section families would draw two different lines.
