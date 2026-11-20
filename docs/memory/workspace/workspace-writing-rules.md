---
name: workspace-writing-rules
description: How every AI is told to write a Workspace message (bold, code, lists, tables, quotes, links, no headings): one English text in code for the agent instructions and the guide
---

## Why is the rule in the software?
- Owner (2026-11-20): agents drew lists and columns as plain lines in the channel; the rule must reach every site's agents from the software (the agent instructions and the AI guide), not from a channel's memory.

## Where is the text?
- `includes/workspace/writing_rules.php` (#10774), pure, English, edited by no screen (as `includes/fn/ai_prompts.php`): `ws_writing_rules_lines()` (the lines, at most `WS_WRITING_RULES_MAX` = 800 characters together; 623 at AW1), `ws_writing_rules_short()` (one sentence), `ws_writing_rules_examples()` (the examples the lines quote) and `WS_WRITING_RULES_POINTER` (one sentence naming `GET /guide?topic=workspace&section=writing`).
- Only marks `includes/workspace/render.php` draws are named: bold, inline code, a list running to the paragraph's end then an empty line (`workspace-text-lists.md`), a table (header, row of dashes, rows), a "> " quote, a tag, a link. A heading stays text in a message (notes alone draw it).

## How is a pull request named?
- By its full address, best as `[#12](https://github.com/o/r/pull/12)`: the renderer draws a bare https address and `[words](address)` as links; a bare `#12` is no tag and stays text (checked at AW1, #10774).

## Who reads it?
- The agent instructions (`agent-instructions.md`): `ws_agent_instructions_writing_lines()` sits between the request lines and the never-list, each line an item. Adding it changed every agent's `instructions_version`, so a session reads the text again at its next brief.
- The guide's workspace topic, section `writing` (`api/agent-guide-workspace-erp.md`), so `GET /guide`, `guide_read`, the MCP resource and the panel's door give it.
- Not yet: the `text` field of the send, edit and answer routes ending with `WS_WRITING_RULES_POINTER` (AW1b, after the two open PRs on `includes/workspace/api.php`), and the assistants' prompts taking the short form (AW2), both in `plans/agent-writing-rules.md`.

## What do the tests hold?
- `tests/workspace_writing_rules_test.php`: each example drawn through `ws_render_body()` as said (strong, code, ul, table, blockquote, a link), a heading and a bare `#12` left text, the length, the short form and the pointer one sentence each, the instructions carry the block before the rules and their version differs without it, the guide lists `(#writing)` within the topic's size.
