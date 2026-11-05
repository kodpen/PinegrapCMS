---
name: api-single-value-fields
description: The external API refuses a JSON list or object in a field that takes one value (422), before any cast.
---

## What does the validator do with a list or object in a one-value field?

`api_validate_value()` (`includes/api/router.php`) asks
`api_value_is_single()` above its type switch: for every type but `list` and
`object`, a decoded JSON array or an object answers 422 `validation_failed`
("… must be a single value, not a list or an object."). The same holds for a
query string `?q[]=x` and for MCP tool arguments, which pass the same
validator.

## And the items of a list?

A `list` declared with a one-value item type (`'of' => 'string'` or
`'integer'`, e.g. webhook `events`, `country_codes`, `ids`) refuses a nested
list or object as an item with 422 ("… must hold single values, not lists or
objects."), through `api_list_items_are_single()`. A list whose `of` is a
field map (objects such as `options`) or that has no `of` is checked by its
handler. Before, `{"events": [["order.created"]]}` reached the handler's
`(string)$event` and printed "Array to string conversion" before its 422.

## Why?

`(string)` on an array gives `"Array"` and a PHP warning. A `string` field
sent as `{"name": ["Europe"]}` passed validation and stored "Array" with 200
(`max_length` measured "Array"); `enum`, `bool` and `datetime` fields printed
the warning before their own 422. Fixed for bug #6801.

## Where is it tested?

`tests/api_single_value_test.php`.
