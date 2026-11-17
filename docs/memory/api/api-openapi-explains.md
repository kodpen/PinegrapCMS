---
name: api-openapi-explains
description: What the OpenAPI document adds beyond the route table: a sentence for each tag, generated id descriptions, one example body per write route, all kept out of the MCP tool list
---

## Where does a tag's sentence come from?
- `api_openapi_tag_sentences()` (`includes/api/openapi.php`): one hand-written sentence a group, by the tag `api_openapi_tag()` gives a route (`Product_groups`, `Erp`). `api_openapi_tags()` builds the document's `tags` list from the groups in use.
- A new group (a new first part of a route id) needs its sentence: `tests/api_openapi_explains_test.php` fails on a group without one, and on a sentence without routes.

## Which descriptions are generated?
- An id parameter (`id`, `*_id`, `*_ids`) with no `description` in its route row gets one from `api_openapi_id_description()`: a bare `id` names the path ("Id of the record at /store/zones/{id}."), `account_id` gives "Id of the account.", `calendar_ids` "Ids of the calendars, as a list."; `since_id`, `before_id`, `after_id` and `ref_id` have their own sentences.
- A written `description` always wins. Write one when the id is not plainly the record its name says.

## Where do example bodies come from?
- `api_openapi_example_body()`: every body field with `api_openapi_example_value()`'s value (smallest accepted number, first enum value, one list item). It sits in `requestBody.content.application/json.example`.
- The same function fills the request `tests/helpers/api_route_examples_run.php` sends through `api_validate_input()`, so every example body is one the router takes. Never write an example by hand.

## Why does the MCP tool list not grow?
- The tool list (`pg_mcp_tools_list()`) reads the route rows; the generated texts and examples are added only while the document is built. PR #PRNUM: 727 tools, 1,021,067 bytes before and after. A test checks the tool list carries none of them.
