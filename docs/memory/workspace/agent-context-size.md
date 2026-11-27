---
name: agent-context-size
description: An application agent's context window size typed on its form (ws_agents.context_size, step 11.0.280): the check, the reader's order form then report, what is drawn without a size
---

## Where is the size kept?
- `ws_agents.context_size` INT UNSIGNED, 0 for not entered (step 11.0.280; `workflow/decisions-made-schema-53.md`). Helpers in `includes/workspace/agent_report.php`; tests in `tests/workspace_agent_report_test.php`.
- Why: a session cannot read its window's size; most agents sent no context and k3 sent a guessed 1M for a 250k window.

## How is it set?
- The "Context window (tokens)" field of an application agent's form, under How it is reached, after Application. Not on a model assistant's form.
- Read as `token_limit` is (`agent-token-limit.md`): `ws_agent_context_size_check()` in `ws_agents_settings_check()`; empty is 0, spaces, dots and commas dropped, a whole number up to 10,000,000. `ws_agent_context_size_save()` writes it after `ws_agent_save()`.
- The application's agent setup tab posts no size; `ws_agent_app_setup_fields()` keeps the stored one.

## What does the reader give?
- `ws_agent_report_context($agent)`: used, size, percent, words. The size is the form's, else a size the agent reported, else none. A reported size counts only while the form's field is empty.
- No size: words "Context 412k tokens", size and percent null; no ring, bar, percent or 90% mark (card, Channel info's agents block).
- Used above the size: percent 100, drawn full; the report is never refused for it.

## What does the agent send?
- The tokens it uses: `"context": {"used": n}` on the report or the answer route, `php tools/ws_profile.php --context=USED`, or `--context=auto`, which reads them from a Claude Code session's transcript (the last message's input, cache creation and cache read tokens together; `workflow/claude-context-reader.md`).
- The instructions and the brief say never to guess the window's size. `{used, size}` is still taken from a harness that reads its own.
