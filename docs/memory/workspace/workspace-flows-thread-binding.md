---
name: workspace-flows-thread-binding
description: Drawing a data thread into a field on the Flows canvas (T2, PR #2540): live dots, drag, two taps or Fork, the lit fields, bindValue(), the keyboard's Pass to a field
---

## How is a thread drawn into a field?
- In a draft every drawn dot, and every value of the selected box (`boxValues()`, unused dashed), is `.wsf-data-live`. Dragging one (`drag.kind` `thread`), or two taps on it (the captured pointer sends no dblclick), or its menu's Fork (`K.onContext`: right-click, long press) sets `state.binding` and lights the fields it may go into (`.wsf-data-target`, `data-bind`); the next tap or the drop binds, anything else or Esc lets it go.
- `bindTargets(from, token)`: the `openFields()` of every box `valueSources()` lets read it from that box, none already holding it; addresses and recipients are not in `VALUE_FIELDS`, so never lit. A condition's only while its value rule compares nothing.
- `bindValue()` writes the token at the field's end after a space (the chip "Add value" draws), refuses it over the field's maximum (`fl_thread_full`); a condition's `rule.ref`, its op back to "is" when it does not fit the type.
- Keyboard: V on a box, or the menu's "Pass to a field…": `askBind()`, a value and a field.
- Test `test_workspace_flows_canvas_thread_binds_into_the_fields_after`.
