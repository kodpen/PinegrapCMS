---
name: designer-form-logic-steps
description: Steps FL1-FL4 of plans/designer-form-logic.md: Shown when rules, form steps with progress, the browser draft, the fee setting and order writer; claim as [plan:designer-form-logic] FL<n>
---

## Which run side by side?
- Wave 1: FL1 and FL2 at once (FL1 touches the rules code, FL2 the renderer and palette; both edit `style_designer.src.js` in different functions, so the second to land rebases). Wave 2: FL3, FL4, FL6 at once after their "After" steps. Wave 3: FL5, then FL7. Wave 4: FL8. FL5-FL8: `designer-form-logic-steps-2.md`.
- S under 400 product lines, M about 1,000. Tests in `tests/custom_form_<topic>_test.php` (pure functions, red first; the parity test renders a rule-less form before and after), memory under `designer/`, texts through `tr_add.php`, `php tools/build_js.php` after the designer script, `node --check` on `style_designer.src.js`.

## Which steps are there?
- FL1 (M) Shown when. `free` after question 1.
  - `pg_cf_rules_clean()` accepts `shown_when` (field name + answers, the `required_when` shape); `pg_cf_rule_verdict()` returns `hidden` for a field whose rule does not match, and `pg_cf_check_rules()` then drops the posted value and skips required (question 1); `pg_cf_apply_field_bindings()` writes `data-pg-shown-when` and `data-pg-shown-answers`; `pg_cf_validation_script()` toggles `hidden` on the field's row and `disabled` on its controls on change and at load; a hidden field that is the "other field" of another rule cascades. Designer: a "Shown when" row in the Form Field panel beside "Required when" (`_cfRulesOf()`, `_cfWriteRules()`), a chip on the canvas control. A cached page re-evaluates at load, so stale markup never shows a wrong branch. Memory: `designer/custom-form-shown-when.md`.

- FL2 (M) Steps and progress. `free`.
  - Palette control "Step" (`CF_PALETTE`): a break node with a title; `_pg_cf_controls()` ignores it as a field; the site renderer (`custom-form-render.md`) groups the controls between breaks into `<fieldset class="pg-cf-step">` with `<legend>`, draws Back and Next buttons, a progress line ("Step 2 of 4", `aria-current`), and keeps one `<form>`; `pg_cf_steps_script()`: Next runs `reportValidity()` on the visible step only (hidden fields of FL1 excluded), focus moves to the step's first control, the browser's back key is not hijacked; a form without a break renders as today. Site CSS in the widget's stylesheet section on `--pg-*` tokens. Tests: the grouping function with breaks at the start, in the middle, at the end, none. Memory: `designer/custom-form-steps.md`.

- FL3 (S) Browser draft. After FL2.
  - The answers of a stepped form are kept in `sessionStorage` under the page id and record kind (never file inputs, never a signature, never a card field), restored at load, cleared on a successful submission and on "Start over"; off when the form sits in Preview or prototype play. Memory: `designer/custom-form-steps.md` grows.

- FL4 (M) Fee setting and the order writer. After questions 2-3.
  - Form settings (the designer's custom form settings sheet, `custom-form-settings-shortcut.md`): "Payment" with the fee product pick, amount source (product price or a numeric field with min and max), offline payment allowed; stored with the page's form settings (no schema when `custom_form_pages` has a JSON settings column; else one TEXT column by a sub-step, asked with question 2). `includes/fn/form_payments.php`: `pg_form_fee($settings, $values)` (pure), `pg_form_payment_order_write($record_id, $fee, $contact)` modelled on `pg_subscription_renewal_order_write()` (one `order_items` line, billing from the record's contact fields, `orders.notes` untouched), the record's system fields `order_number` and `paid` (`submitted-form-system-fields.md`), `pg_form_payment_mark_paid()` called from `pg_order_complete()` for an order that carries a record id in its context. Tests: the fee function, the completion hook. Memory: `designer/custom-form-payment.md`.
