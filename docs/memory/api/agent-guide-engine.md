---
name: agent-guide-engine
description: The guide for AI agents (agent_guide.php): topic registry, topic files, GET /guide (guide.read, no scope), MCP guide_read, pinegrap://guide resources, "Guide: <topic>." on tools, size limits
module: agent_guide, agent_guide_start
---

## What is the guide, and where does a topic live?
- `pg_agent_guide_registry()` (`includes/fn/agent_guide.php`) names eight topics: start, designer, content, forms, store, workspace, erp, panel. Each: title, when (one sentence), module (`''`, store, workspace, erp), tools (route id prefixes), file.
- A topic's text is built by its own file, `includes/fn/agent_guide_<id>.php`, declaring `pg_agent_guide_<id>_sections()`: a list of `id` (`[a-z_]`), `heading`, `lines`. A line is text, or `array('text', 'module')` kept only while that module is on. A topic whose file is not there is not listed: adding a topic is adding its file, the engine is not touched.
- Built in #10710 (plan ai-guide, AG1): only `agent_guide_start.php` exists; the other seven come with AG2 to AG5.

## How does a line name a tool?
- As `{tool:route.id}`; the engine writes the tool's name (dots as underscores). `tests/agent_guide_test.php` fails on a named route that is no route or no MCP tool, so a renamed route cannot leave the guide behind. Never type a tool name in a line by hand.

## Who sees which topic?
- `pg_agent_guide_listed()`: the file is there, the module is on, and a topic with tool prefixes is listed only to a caller whose scopes cover a route starting with one. start and panel (no prefixes) are listed to everyone.

## What does GET /guide answer?
- `guide.read` (`includes/api/resources/guide.php`): `topic` and `section` optional. Answer `version`, `digest` (16 hex of sha256 of `text`), `topic`, `section`, `topics` (the listed ids), `text` (Markdown; headings end `(#section)`). Without topic: the topic list.
- Scope `''` but not public: any valid credentials, as `auth.me`. A topic not listed or a section it lacks: 404 not_found; a section without topic: 422 validation_failed. The ETag and 304 come from `api_send()` as on every GET.

## How does MCP give it?
- `'mcp' => true` on the route: `pg_mcp_route_is_offered()` offers a scope-less route only with it, so `guide_read` is listed to every caller; `auth.me` stays out.
- Resources `pinegrap://guide` and the template `pinegrap://guide/{topic}` (topic `[a-z][a-z_]{0,31}`) over the same route (`mcp-resources-prompts.md`).
- `pg_mcp_instructions_guide()`: two lines in the opening instructions, between the conventions and the rules.
- `pg_mcp_tool_guidance()` ends a tool's description with "Guide: <topic>." when its route falls under a listed topic's prefixes (`pg_mcp_tool_guide_line()`); the form lines of `mcp-form-guidance.md` now sit in `pg_mcp_tool_guidance_own()`. MCP only: the OpenAPI document keeps the route text.

## Which sizes hold?
- `pg_agent_guide_sizes()`, held by the test: topic list 1,536 bytes, a topic 4,096, the opening instructions 3,072. A subject that needs more is a second topic.
- Measured at AG1: start 2,448 bytes with every module on; the list of all eight 814; the opening instructions 2,881 (the two guide lines 354).
