---
name: workspace-flows-template-table
description: Saved flow templates ask the table of a row box (PR #3597): the table setting, the setup panel's picker, the reach refused at Use, the fields a changed table clears
---

## Which table does a row box ask?
- Setting type `table` (row, row_add, row_set: `table_id`); table 0 is not asked. The setup panel's picker (`setupTables()`, label `fl_tpl_set_table`) offers `FL.steps.tables`, only `edit` ones once a target adds or changes rows; a default the person does not reach shows as none.
- Rights: `ws_flow_template_setup()` refuses (403, field `table`) a table below `ws_flow_template_table_need()` (edit for row_add/row_set, view for a row start) by `ws_wtable_access()`: `ws_flow_template_table_refusal()`.
- A changed table clears the old table's fields in `ws_flow_template_apply()` (`ws_flow_template_table_cleared()`): a row start's `column_id`, the cells, a row_set's literal row; a `{{…}}` row stays.

## Why?
- Before, a template saved with a row, row_add or row_set box kept its author's table: a flow made from it pointed at a table the new person might not reach.
