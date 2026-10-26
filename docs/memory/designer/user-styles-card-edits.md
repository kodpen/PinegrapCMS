---
name: user-styles-card-edits
description: How the Styles panel's User Styles cards read and edit the User Styles text (assets/js/pg_user_styles.js): which rules are listed, edits in place, Create as one undo step
---

## Which rules does the Styles panel show as User Styles cards?
- `PgUserStyles.parse(text)` (`assets/js/pg_user_styles.js`, loaded before `style_designer.js` in `designer_screen.php`): style rules at the top level and inside `@media`, `@supports`, `@container`, `@layer` (the wrapper shown on the card, `rule.media`). Not listed: `@font-face`, `@keyframes`, `@page`, nested rules (a body holding a block), comments. A `;` or `}` inside a string or `url()` stays in its value.

## How does a card edit change the text?
- Every handler re-parses the current text and calls one writer (`setSelector`, `removeRule`, `setDecl`, `removeDecl`, `addDecl`): only that rule's or declaration's characters change; the rest stays byte for byte. `_writeUserStylesText()` takes one `saveState()` first.
- Before, the list was serialised back as the whole text: comments, `@font-face` and `@keyframes` were dropped, and a rule inside `@media` lost its wrapper and applied at every width (design templates ship such rules). Fixed in PR #1721.
- A one-line rule stays on one line when a declaration is added (a missing `;` is added); a new line takes the last declaration's indent; a removed declaration or rule takes its line when nothing else is on it.

## Is Create undoable?
- Yes: `_stylesCreateRule()` writes `PgUserStyles.appendRule()` through `_writeUserStylesText()`, one undo step. Before, it wrote without `saveState()`.
- `_sdEnsureBlockStyles()` still appends through `_userStylesSet()`: it runs inside a drop that already took its snapshot.

## Where are the tests?
- `tests/designer_user_styles_test.php` runs the helpers under Node on a template-like text and checks the wiring (Create, script order).
