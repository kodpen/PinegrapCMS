---
name: designer-form-logic-steps
description: Steps FL1-FL4 of plans/designer-form-logic.md: Shown when rules, form steps with progress, the browser draft, the fee setting and order writer; claim as [plan:designer-form-logic] FL<n>
---

## Which run side by side?
- FL5b and FL6b after their "After" steps, FL7 after FL5, FL8 last. FL5-FL8: `designer-form-logic-steps-2.md`.
- S under 400 product lines, M about 1,000. Tests in `tests/custom_form_<topic>_test.php` (pure functions, red first; the parity test renders a rule-less form before and after), memory under `designer/`, texts through `tr_add.php`, `php tools/build_js.php` after the designer script, `node --check` on `style_designer.src.js`.

## Which steps are there?
- Done: FL1 #6130, FL2 #5932, FL3 #6011, FL4 #6128 #6129.
