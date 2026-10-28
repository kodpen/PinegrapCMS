---
name: ai-prompt-data-blocks
description: Untrusted text set apart in AI prompts, tool answers and the designer's page brief (pg_ai_data_block(), PR #1854, #1953): the marks, forged marks, what is framed
---

## How is untrusted text set apart?
- `pg_ai_data_block($name, $text)` (PR #1854): a `<<<name>>>` line, the text, a `<<<end name>>>` line. Every `<<<` in the text is rewritten as `‹‹‹`, so a forged closing mark cannot end the block early and the only marks are the block's own; the name keeps only letters, digits and spaces.
- Pinegrap AI and Claude's brief (`ws_ai_briefing()`, which `ws_ai_start()` takes): request, note (title inside), task JSON, channel context, the discussion's or replied-to message, people, earlier agents' answers. The "Answer in …" line stays outside, last.
- Tool answers (`ws_ai_tool_message($call_id, $data, $note)`): the data in a `tool answer` block, cut at 12,000 characters inside it; the site's own words about the call (invalid arguments, an answer held back) go in `$note` before the block, because words inside would no longer instruct.
- The designer's page brief (`pg_design_ai_brief()`, built for `pg_design_ai_start()`, PR #1953): the page or selected element in a `page` block, a cut page's outline in `page outline`, the CSS in `page css`, the request in `request`. "The page is longer than shown…", "Where it stands…" (a `<<<` in a parent's name neutralised there too) and "Answer in …" (last) stay outside.
- Claude reads the same blocks through GET /workspace/claude/requests/{id}/brief (workspace/workspace-claude-bootstrap.md). An MCP agent whose server sends the preamble gets its request in a `request` block (workspace/workspace-agent-preamble.md); with the switch off the message goes as it is. Tests: tests/ai_prompt_framing_test.php.

Related: docs/memory/core/ai-prompt-rules.md, docs/memory/plans/ai-agent-prompts.md
