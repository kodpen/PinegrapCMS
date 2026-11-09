---
name: panel-date-field-opt-in
description: Panel date, date-and-time and time fields open our calendar, never the browser's: the hook a new field needs, the scripts its screen prints, the help tie, the guard test
---

## Which picker do panel date fields use?
- One: the calendar of `assets/js/pg_datetime.js` (vanilla-calendar-pro) that `pg_fields.js` draws on a field with a `data-pg-*` hook (`pg-datepicker.md`; one control for the whole software, #2693 in `workflow/decisions-made-panel.md`).
- The browser's own picker (Chrome: "gg.aa.yyyy", Temizle / Bugün) means a native input without its hook, or a screen that did not print the field script: the panel hooks nothing by type, only site pages do (`site-native-date-inputs.md`).

## How does a new panel field opt in?
- The hook of its type: `data-pg-date`, `data-pg-datetime` or `data-pg-time` (`data-pg-time="hh:mm"`: a 24-hour text time). A native `<input type="date|datetime-local|time">` stays the hidden store and keeps posting ISO (`2026-11-09`, `2026-11-09T17:05`, `17:05`); `min`/`max` bound the calendar, `required` stays on it. A text input keeps the site's text (`pg-date-stores.md`). Month and week inputs have no control: use a date.
- Print `get_date_picker_format() . pg_fields_script()` with the field, in the part that draws it (a settings card, a dialog), never only in a part printed now and then: the orders list prints them only with its advanced filters open. `pg_fields_script()` prints once per request, so a call whose output is dropped leaves later fields plain.
- Built by a script: set the type and the hook together (`pgDateField()` in `workspace.js`), put it in the page, `pgFields.init(node)`; write values with `pgDatePicker.set()` or `pgFields.refresh(el)`.
- Help text: `pgBindFieldHelp()` skips date hooks; tie it by hand (`aria-describedby` on the field, the id on the help). The box takes the field's `aria-describedby`, `aria-label`, `placeholder` and `title`.

## Where does the calendar open?
- Under the field; above it when it does not fit below and there is more room above (`opensAbove()`), in a dialog host and on the page: a dialog does not scroll, and the last field of a screen has no page under it.

## Which fields were the last on the browser picker?
- Owner's screenshot (2026-11-09): Site Settings > Privacy > Accessibility Statement > Statement Date. Also the product pre-order Ships From date, Edit User > Store Credit > Last day, and the Request Pickup dialog's Earliest and Latest time (order screen, orders list; its date, hooked before, was native on the list without advanced filters). PR #8383 hooked them, each with its store: Y-m-d (`pg_a11y_statement_clean()`, `pg_preorder_date_parse()`, `pg_credit_last_day_expires()`), H:i (`pg_shipment_label_pickup_window()`).
- Not changed: site widgets' native inputs (cart, express order: the site hooks them by type), the designer's canvas previews and palette parts (site markup).

## What keeps it that way?
- `tests/other_date_fields_test.php` `test_other_date_fields_no_native_input_left_on_the_browser_picker`: every `<input>` of type date, datetime-local, time, month or week in the product tree carries the hook of its type, and no script sets such a type as a literal; comments skipped. Left out as site markup: `includes/fn/widgets_*`, `includes/design_templates/`, `includes/templates/`, `style_designer.src.js`, `frontend.src.js`.

Related: docs/memory/ui/pg-datepicker.md, docs/memory/ui/pg-date-stores.md, docs/memory/ui/pg-field-wrappers.md, docs/memory/ui/field-help-describedby.md
