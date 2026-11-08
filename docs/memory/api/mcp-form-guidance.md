---
name: mcp-form-guidance
description: What MCP tells a model before it writes a form record (PR #8042): guidance on the form write tools, the pinegrap://forms/{id}/fields resource, the blog_post prompt and its text argument
---

## What do the form tools say beyond their route's description?
- `pg_mcp_tool_guidance()` (`includes/mcp/tools.php`) appends a line to `forms_submission_create` and `forms_submission_update`: read `forms_fields` (or the resource) first, an answer is addressed by the field name, a choice answer is one of its option values, a `rich_text` field keeps only what its `html_allowed` lists (never `class`) up to `max_length`, try with `dry_run` first. `forms_get` points at `forms_fields`.
- MCP only: the OpenAPI document keeps the route's description. It sits in the MCP layer so the forms route rows stay as the forms steps write them.

## Which resource reads a form's fields?
- `pinegrap://forms/{id}/fields` (`form_fields` in `pg_mcp_resource_kinds()`) over `forms.fields` (GET /forms/{id}/fields, `forms:read`), with every rule of `mcp-resources-prompts.md`. The route test there reads `api_schema()` beside the Workspace routes.

## What does blog_post do?
- Arguments: `form_id` (required id), `topic` (optional text). Offered when the caller may read `form_fields` and `forms.get` and `forms.submission_create` are among its tools (the kind's `tools` list, checked by `pg_mcp_prompt_is_offered()`).
- It embeds the fields resource when the route answers the caller (as channel_summary embeds the context). The text: read the form with `forms_get` (`is_collection`, `record_url_pattern`, `framework`); put the post on the fields by label; a status that publishes only when the person asked to publish; the body as HTML within `html_allowed` and `max_length`, no framework classes; pictures through `files_create` when the caller holds that tool (its `url` is on the site's host, which `html_allowed` keeps), else leave them out and say so; send with `dry_run` true, then for real; report the id, reference code and address. Ends with `pg_ai_prompt_rules('assistant')`.

## How is a text argument checked?
- An argument with `'kind' => 'text'` is the person's words: control characters become spaces, trimmed, 1 to `pg_mcp_prompt_text_max()` (500) characters, else -32602 "Invalid argument: topic must be text of 1 to 500 characters." (never cut). Empty or null is "not given". Every other argument is an id.

## Where is it tested?
- `tests/mcp_blog_post_test.php`.
