---
name: agent-writing-rules
description: Plan (owner 2026-11-20): the software tells every AI how a Workspace message is written (lists, tables, code, bold); one text for the agent instructions, the AI guide, the assistants; AW1, AW2
priority: 7
---

## What is asked?
- Owner (2026-11-20): "kanalda sen doğru tekniklerle konuşmana rağmen diğer ajanlar araçları kullanmıyor sebebi nedir?", with a coordinator's column list drawn as plain lines.
- Owner (2026-11-20), offered a line in the channel's memory: "kanal belleği bu sohbette iş görse de yazılımı kullanan diğer kullanıcılar için bir anlam ifade edemez … ai talimatlarında hem arayüzden hem de api den talimatlar ekleme planımız vardı bu alanda mesajla ilgili de talimatlar olsun."
- So the rule is the product's: every site's agents read it from the software, by the doors of `plans/ai-guide.md` (API, MCP, the panel) and the agent instructions; no channel memory entry.

## What is there today?
- The agent instructions say only "short and concrete"; the assistants' prompts "Markdown is shown, but use no headings". No text says which marks the Workspace draws.
- The coordinator's list had a line after it in the same paragraph, so it stayed text (`workspace/workspace-text-lists.md`). The list rule stays: texts stored before lists were drawn keep their look.

## What is built?
- (W1) One text, in code: new `includes/workspace/writing_rules.php`, a pure function giving the lines in English, at most 800 characters together, and a short form of one sentence. Only marks `includes/workspace/render.php` draws are named.
  - The lines: bold for a decision or a name to be found; code marks for a file, a function, a column, a command; a list as lines that run to the paragraph's end, an empty line after it; columns, options and figures as a table, an empty line after it; a quote; a message by its tag; no headings.
- (W2) The agent instructions (`includes/workspace/agent_instructions.php`) carry the lines; their version changes, so a session reads them again at its next request. The brief is not changed.
- (W3) The AI guide's topic `workspace` gains the section `writing` built from the same lines (`includes/fn/agent_guide_workspace.php`), so `GET /guide`, `guide_read`, the MCP resource and the panel's door give it. The topic stays within 4,096 bytes: the worker shortens the section first and asks the architect when it still does not fit.
- (W4) The `text` field of the routes that write a message (send, edit, an agent's answer) ends with one short sentence naming the section, from one constant.
- (W5) A pull request's reference is named only if the renderer draws it as a link; the worker checks and says which form it found.
- (W6) The assistants' prompts (Pinegrap AI and its MCP answers in `ai.php` and `mcp_ask.php`, Claude's routine in `claude.php`) take the short form in place of their own Markdown sentence.

## Which steps are there?
- AW1 (S) after AG4 of `plans/ai-guide-steps.md` has landed: W1 to W5.
  - AW1 #10774: W1–W3, W5. AW1b: W4 (`WS_WRITING_RULES_POINTER`), after #10751 and #10742 have landed.
- AW2 (S) after AW1: W6. The prompt rules' test still passes; this plan deleted in the same PR.
  - Seen on dev: an agent's answer written by the examples.

## What binds it?
- `includes/workspace/agent_instructions.php` is also entered by AA7 of `plans/app-access-redraw-steps.md`: AW1 goes first.
- `agent_guide_workspace.php` is also entered by AG10 of `plans/ai-guide-steps-2.md`: AW1 goes first.
- The text stays in English and in code, as the prompt rules (`includes/fn/ai_prompts.php`): no screen edits it.

## What is left out?
- Instructions a person writes for every agent on a screen or over the API: a later yes; an agent's own text is its Duty (`workspace/agent-duty.md`).
