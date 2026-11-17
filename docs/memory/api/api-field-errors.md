---
name: api-field-errors
description: External API 422 lists every wrong field in error.errors (field, code, message; at most 20); a JSON list as body is 400 invalid_json, [] an empty body (#10156)
---

## What does a refused call answer?
- 422 `validation_failed` with `error.field` and `error.message` of the first refusal, as before, and `error.errors`: every refusal of the call as `{field, code, message}`, in declaration order, unknown body keys last, at most 20 rows (`api_field_errors_max()`).
- Before, the router stopped at the first wrong field, so a client found its mistakes one round trip each.

## Which checks fill error.errors?
- Only the router's own: `api_validate_collect()` (`includes/api/router.php`) over the declared parameters and the body keys no parameter takes. `api_validate_input()` calls it and answers through `api_fail_field_errors()`; the MCP tools run the same function.
- A handler's refusal (`api_fail_validation()` in a resource file) stays one error, with no `errors` list.
- Inside `api_validate_value()` and `api_validate_bounds()` a refusal is `api_field_refuse()`, which throws `ApiFieldRefused`; `api_validate_collect()` catches it and goes on. They are not meant to be called outside it.

## Which row codes exist?
- `required`, `unknown_field`, `not_single`, `not_integer`, `not_minor_units`, `not_number`, `below_min`, `above_max`, `not_boolean`, `not_allowed` (enum), `not_date`, `not_object`, `not_list`, `too_many_items`, `items_not_single`, `too_long`. The sentences are the ones a single refusal gave before.

## What about a JSON list as body?
- A JSON body that is a list with items (`[{"name":"Pen"}]`) is 400 `invalid_json`, "The request body must be a JSON object, not a list.", as a bare value already was (`api_json_body_is_list()`). Before, its 0, 1, 2 were read as field names and the answer was "name is required".
- An empty list `[]` stays an empty body: `json_encode()` writes an empty PHP array so, and clients send it for "nothing".

## Where is it tested?
- `tests/api_field_errors_test.php`. The OpenAPI `Error` schema and every operation's error answer declare `errors`; the API Documentation page's Conventions entry "Errors and X-Request-Id" says it. Step AM8 of plan api-clean-modern, PR #10156.
