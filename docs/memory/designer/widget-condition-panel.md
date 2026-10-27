---
name: widget-condition-panel
description: The Bind Data row "Show only when a value…" (#1665): token, operator, compared text, the canvas chip, the twin lists, no condition on a required control, the compared text translated.
---

## Where does an operator set a value condition?
- Bind Data, row "Show only when a value…" (`_sdConditionRows()` in `style_designer.js`), on any element inside a system widget but its root and its loops (`root`, `loop_area`, `recipient_loop_area`). It writes the server's shape ([[widget-conditions]]): `_bindings.show_if` through `_sdNodeSetBinding()` (marks the widget dirty) and `_bindParams.show_if = {op, value}` (`_sdCondWrite()`). "Always" clears both; an operator without a value keeps none.
- The value list is the element's Bind Data tokens (`_swBindTokenOptions()`, the row position group inside a list) minus the refused ones (`_sdCondTokenAllowed()`). The flag row "Show only when" stays where a kind has one; both must pass.
- A new value redraws the panel; the operator and the compared text change in place (one undo step per choice and per word typed).
- Up to three rows with an All/Any switch ([[widget-condition-rows]]).

## What does the canvas show?
- A chip at the element's top left: "Only when: <value> <operator> <compared text>" (`_sdCondSentence()`, three numbered parts so Turkish orders them `{var:1} {var:3} {var:2}`). It names the condition; whether the canvas draws the element is the band's state ([[widget-condition-canvas]]). Hidden on `.sd-fx-flat` wrappers and while the element's text is edited.

## Which lists must match the server?
- `SW_COND_OPS` = `pg_sw_cond_ops()` (order and "takes a value"); `SW_COND_DENY` = `pg_sw_cond_denied_tokens()`; `_sdCondTokenAllowed()` = `pg_sw_cond_token_allowed()`. `tests/widget_condition_panel_test.php` checks all three.
- No list name holds TOKEN: `tools/check_bindings.php` reads `SW_*TOKEN*` arrays as palettes, and the refused tokens would count as offered.

## Why can a required form control not be hidden?
- Owner decision (2026-10-25): the server still asks for a required control's value, so a form whose required control a condition cut could never be sent. The panel offers no condition on an element that is or holds a control with a `required` attribute (`_sdCondHoldsRequired()`); the renderer ignores one set by hand or by a template (`pg_sw_cond_holds_required()` in `_render_tree_node()`), and the panel says so.

## Is the compared text translated?
- Yes, owner decision (2026-10-25): the value it is compared with is drawn in the page's language (form records through `translate_records`), so a compared text with words is a translatable field `_cond:show_if` (`pg_sw_cond_operand_is_words()`: eq, neq, contains, not_contains, starts; not a number or an amount). The renderer reads it in the page's language before it reads the condition (`pg_tr_condition_props()`). The Translate section lists it as "Condition: compared with" ([[designer-content-translation]]).
- Not on a code token (`pg_sw_cond_code_tokens()`: `__step_kind`, `__order_status_stage`, #1808; `__crumb_kind`, #1945): its value is the same in every language, and a translated "complete" would hide the order view's status badge and timeline icons, a translated "active" every crumb of the catalog breadcrumb.
