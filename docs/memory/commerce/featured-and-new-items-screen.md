---
name: featured-and-new-items-screen
description: How Edit Featured & New Items checks the featured order, what it shows after a save, and its layout
---

## What does the Featured Order box accept?

`pg_featured_sort_order_value()` (`includes/fn/ecommerce.php`): `''` (not
featured), or a whole number 0–65535 (`featured_sort_order` is smallint
unsigned), else `false` and the save stops with an error naming the item.
MySQL used to store `abc` / `-5` as 0 and `12,50` as 12 while the screen said
saved (#5459). The box uses the `integer` input mask, `maxlength` 5.

## What do the boxes show after a save?

The stored values: the screen prefills from the database unless the form
carries an error (`$prefill = !$liveform->check_form_errors()`), and it
assigns `''` to items that are not featured or have no new date. Before, any
form left in the session (the success notice too) kept the typed values.

## Layout and accessibility?

The table sits in `.table-responsive` and the item cell has `text-break`, so
the page stays 375 px wide (#5461). Both boxes carry an `aria-label`
("Featured Order of …", "New on Date of …"). It stays a plain table, not a
DataTable (the save clears every item whose boxes are not posted).
