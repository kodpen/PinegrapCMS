---
name: custom-style
description: Per-element custom style: a User Styles rule keyed by a pgs- class, pg_custom_style.js, the Styles "This element" card and Attributes css row, Split, where it is off
---

## What is a custom style?
- One User Styles rule, `.pgs-xxxxxx { … }` (`pgs-` and six of `a-z0-9`), and that class on the element. Extra CSS stays in User Styles, never in the style attribute ([[designer-block-style]]). Export keeps the rules like any other.
- The base rule is the one whose selector is the class alone, outside `@media`. A new base rule goes before the class's `@media` rules (they keep winning at their widths), else at the end.

## What does assets/js/pg_custom_style.js do?
- `window.PgCustomStyle`, pure, carried by the designer page; it edits the text through PgUserStyles ([[user-styles-card-edits]]). `classOf`, `newClass(taken, rand)`, `find`, `body` (one declaration per line), `write(text, cls, value)`, `split(text, cls, next)`, `users(classLists, cls)`, `warnings(value)`.
- `write` ends each declaration with `;` when the next line starts a new one (before a closing comment), keeps a list that goes on over lines, removes the rule for an empty value and refuses a brace outside strings and comments (`brace`), which would end the rule early.
- `warnings`: `important` (allowed, with a note that it beats Bootstrap classes and the theme), `brace`.

## Where is it edited?
- A "This element" card above element.style (`_sdCustomStyleCardHtml`): a growing textarea with the light dropdown (`style` kind, [[style-fields]]), written on `change` (blur) by `_sdWriteCustomStyle()`: `_sdMayEdit`, the design lock, one `saveState()`, the class added the first time, the rule written, `render()`. Emptied, the rule goes and the element drops the class. A rule-only change does not touch the tree.
- The same field (`_sdCustomStyleFieldHtml`, `_sdBindCustomStyle`) is the Attributes panel's `css` row below `class` (`_sdCustomStyleRowHtml`); there only the User Styles lock is drawn on the row, the panel draws its own.
- Locked with `_sdPanelLock` at content level (User Styles are never editable there, [[designer-content-roles-panels]]). Hidden while Find filters the panel.

## When is it shared or off?
- A copied element keeps the class and so shares the rule; the card says "N elements use this style" (every page's tree) with Split (`_sdSplitCustomStyle()`: a new class, a copy of the rule, one undo step).
- Off, with the reason shown, for a component placement and inside a system widget or a shared component (`_sdCustomStyleOff()`): their trees are not the page's.

## How is it tested?
- `tests/designer_custom_style_test.php` (Node with pg_user_styles.js), `_sdCustomStyleOff()` on stand-ins. The card and row are DOM work, not unit tested.
