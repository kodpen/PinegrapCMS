---
name: ai-guide-features-2
description: Plan ai-guide, features G11 to G19: the panel's door for a browser agent, the plugin, the compact tool list, the Design and Content presets, size budgets, hints on refusals, the refused calls report
---

## How does a browser agent find the guide?
- (G11) `agent_guide.php` in the software folder: the guide as `text/markdown` for a signed-in panel session (`?topic=`); without a session it answers as any panel page does. Every panel page carries `<link rel="help" type="text/markdown">` to it and a visible link "AI agent guide" in the header account menu (`pg_account_menu()`); the Visual Page Editor links `designer`. `llms.txt` in the software folder answers the topic list the same way.
- Nothing is written for agents that the person cannot see: no hidden text, no instruction in an attribute.
- (G12) The Claude plugin's skills (CB3) call `guide_read` and copy no topic.

## How does the tool list get cheap?
- (G13) The compact list, a second address of the `mcp` resource with its own protected-resource document (`pg_oauth_resource_addresses()`); a token is good for both. Its `tools/list`: `guide_read`, `tools_find` (words, area: at most 20 names with their first sentence), `tools_describe` (at most 5 names: description and input schema), `tools_read` (name, arguments: GET routes only) and `tools_write` (the rest, `dry_run` as the tool takes it).
- (G14) A route marked `'moves_money' => true` is listed on the compact address under its own name and refused through `tools_write`, so the client's question before it names the action. Every call runs `pg_mcp_route_run()`: scope, validation, rate limit and log as today. A tool called by its own name runs on both addresses; the full address is unchanged.
- (G15) Consent presets Design and Content beside Workspace, Store, Accounting and All: the area's reads; record writes stay ticked by hand.
- (G16) Budgets, held by a test: a tool's description at most 600 characters, a whole tool 4,096 bytes; today's tools over them are a list in the test that may only shrink (a listed tool that fits fails the test until it is taken off). What a caller needs for a right call stays in the description, a parameter's description or the named guide section; reference detail moves to a route key `details` that only the OpenAPI document reads.
- (G17) The opening instructions stay under 3,072 bytes (test).

## How does a refusal teach?
- (G18) The error object gains `hint` (one English sentence on what to do next, at most 200 characters) and `guide` (`topic#section`), from a registry by error code (`pg_agent_guide_hints()`) that `api_fail()` reads; a handler passes none. First the designer's refusals (page changed since it was read, unknown element, the inside of a `<pg-keep>` written, a class that draws nothing, the failing operation's index), then validation, `dry_run` and scope. `message` stays the translated sentence.
- (G19) "Refused calls" on the panel's API screen (`api_settings.php`, where the request log is read) and `GET /reports/api-refusals`: from `api_request_log`, the last 30 days by route (ids folded out of the path), error code and application, with the guide section of each code; it shows which section to mend. No new record.
