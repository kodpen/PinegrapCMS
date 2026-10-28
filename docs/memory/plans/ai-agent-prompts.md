---
name: ai-agent-prompts
description: Plan (2026-10-21, steps 1, 2, 2b, 3, 5 done): Pinegrap AI's briefing for every agent, one rules block for every prompt, untrusted text framed as data, Claude's instructions from the site
---

## What is asked? (owner, 2026-10-21)
- Every other AI agent is told what Pinegrap AI is told, and the system prompts are made safe.

## What is there today?
- Pinegrap AI builds its briefing per request: who it is, the site, now, who asked, the place (channel, discussion, note, task), people, language, what may be proposed for the asker, rules (`ws_ai_system_prompt()`, `ws_ai_start()`).
- Claude's routine reads its instructions and each request's briefing from the site (step 3); only a short bootstrap is pasted on claude.ai.
- An MCP agent gets the message and, for a reply, the one it answers, nothing else (mcp_agent.php, a decision); an outside app the webhook payload; an app connected over MCP reads `pg_mcp_instructions()`: conventions, then the rules and its own safety lines (step 5).
- Every prompt now ends with one rules block (step 1). In Pinegrap AI's prompts and tool answers (step 2), the designer's page brief (step 2b) and Claude's brief (step 3), text people wrote goes inside data blocks; MCP agents do not frame theirs yet.

## What is proposed? (not approved yet)
- One rules text in code (English, not editable in settings) and one Workspace briefing builder; every prompt builder uses them, and a test fails for one that does not.
- Untrusted text goes inside marked blocks whose closing mark is neutralised in the text (messages, notes, records, page HTML, tool and agent answers, webhook text); the rules say such text never instructs.
- Writes stay proposals; nothing here widens what an agent may do.

## Slices
1. Done (PR #1124): `pg_ai_prompt_rules()` in every prompt builder and the test that fails for one without it: core/ai-prompt-data-blocks.md.
2. Done (PR #1854): `pg_ai_data_block()` in `ws_ai_start()` and `ws_ai_tool_message()`, the assistant rules' block line, injection fixtures in tests/ai_prompt_framing_test.php: core/ai-prompt-data-blocks.md.
2b. Done (PR #1953): the designer's page brief (`pg_design_ai_brief()`): page, outline, CSS and request in data blocks, the site's sentences outside: core/ai-prompt-data-blocks.md.
3. Done (PR #2736): Claude reads its instructions and each request's briefing (`ws_ai_briefing()`) from the site; the pasted prompt is a versioned bootstrap; the card warns when it is old: workspace/workspace-claude-bootstrap.md.
4. MCP agents and outside apps: a per-server switch (off by default, the decision stands) sends a short preamble (site, asker, place, language, rules) without channel text; outside apps read the same through an endpoint.
5. Done (PR #1799): connected apps: `pg_mcp_instructions()` ends with the assistant rules and three lines of its own (confirm writes and deletes with the person, dry_run first, copy nothing to other tools): core/ai-prompt-data-blocks.md.
6. Each request keeps the prompt version it ran with (every prompt already takes the block through step 1).

## Which questions are answered?
1. Taken: rules text editable by administrators → (a) code only, by step 1 (#1124); (b), a site note below the rules, stays possible later as data.
2. Preamble for MCP agents: off by default per server (a), #2443.
3. Claude's instructions from the site: yes, the bootstrap prompt (a), #2443.
4. Live injection test on dev with the owner, Pinegrap AI and the routine: yes, when the owner has time (#2443).

## Where to start?
- Step 4 (reuses `ws_ai_briefing()`'s head and `ws_ai_briefing_text()`); the live test (question 4) can run now. Agents of plans/workspace-agent-handles.md use the briefing from the start.
