---
name: agent-start-text
description: The "Start text for the agent" box in an application agent's drawer on the Agents card: the short bootstrap it holds, who gets it, the shared Copy field and script
---

## What does the box hold?
- `ws_agent_start_text($base)` (includes/workspace/agent_start_text.php), a pure function, English: the Site API (`ws_claude_api_base()`), HTTP Basic with the application's key and secret from the environment variables `PINEGRAP_KEY` / `PINEGRAP_SECRET`, `GET <Site API>/workspace/agent-instructions` and follow its `text`, read it again when a brief names another `instructions_version`, stop when it cannot be read.
- When the variables are not set, the text has the agent ask the person who set it up which variables hold them; it never guesses or searches for a key.
- Never the key, the secret or the door token: only the variables' names. What the agent does lives in the route's text, so the box changes only when the bootstrap itself changes.

## Who gets the box?
- Only an application agent (`kind` `app`), in its drawer `pgset-ws-agent-<id>` above the activity (`ws_agent_start_box()`, called by `ws_settings_agent_drawer()`). A model agent runs inside the site; Claude has the Claude card's prompt (`workspace-claude-bootstrap.md`). Add an agent's drawer has none.

## Which Copy field does it use?
- The Claude guide's: `ws_claude_copy_field()` with the label as `aria-label`, field id `pgset-ws-agent-start-<id>`. Its listener is `ws_claude_copy_script()` (includes/workspace/claude.php, bound once by `window.pgWsCopyBound`), printed by both the Claude card and `ws_agents_settings_script()`, so Copy works when the Claude card is not drawn.

Test: tests/workspace_agent_start_text_test.php.
