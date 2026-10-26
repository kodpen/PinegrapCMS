---
name: ai-prompt-data-blocks
description: Untrusted text set apart in Pinegrap AI's prompts and tool answers (pg_ai_data_block(), PR #1854): the marks, forged marks, what is framed, the site's own words outside
---

## How is untrusted text set apart?
- `pg_ai_data_block($name, $text)` (PR #1854): a `<<<name>>>` line, the text, a `<<<end name>>>` line. Every `<<<` in the text is rewritten as `‹‹‹`, so a forged closing mark cannot end the block early and the only marks are the block's own; the name keeps only letters, digits and spaces.
- Pinegrap AI (`ws_ai_start()`): request, note (title inside), task JSON, channel context, the discussion's or replied-to message, people, earlier agents' answers. The "Answer in …" line stays outside, last.
- Tool answers (`ws_ai_tool_message($call_id, $data, $note)`): the data in a `tool answer` block, cut at 12,000 characters inside it; the site's own words about the call (invalid arguments, an answer held back) go in `$note` before the block, because words inside would no longer instruct.
- Not framed yet: Claude's routine, MCP agents, the designer's page brief. Tests: tests/ai_prompt_framing_test.php.

Related: docs/memory/core/ai-prompt-rules.md, docs/memory/plans/ai-agent-prompts.md
