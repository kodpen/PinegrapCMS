---
name: widget-address-params
description: How the system widgets read a value from the address (?r=, ?cancelled=, ?query=): one value only, an array is no value; the warnings a later PR fixed
module: widgets
---

## How does a widget read a value from the address?
- Only when `is_scalar()`: `?r[]=x` or `?cancelled[]=1` is read as no value, never cast with `(string)`, which on an array prints "Array" and raises an "Array to string conversion" warning on PHP 8.
- The record view (`_render_system_widget_form_item_view()`, `?r=`) and the order view's cancel notice (`?cancelled=`) in `includes/fn/widgets.php` lacked the check; the comment context, the record address and the searches already had it.
- `tests/widgets_array_params_test.php` keeps the two unguarded casts out.

## What does a visitor see for `?r[]=x`?
- The same as for an unknown code: the record view's not-found state and a 404, without a warning in the log.
