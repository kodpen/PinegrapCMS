---
name: workspace-assistant-key-setup
description: Plan (2026-10-23): Create on the Claude and Pinegrap AI cards makes the linked key; Claude's shows key, secret, prompt once, with a reset
---

## What is asked? (owner, 2026-10-23)
- "Create" on the Claude or Pinegrap AI card makes its Application Access key (every read, Workspace and Tasks writes) and shows prompt, key and secret in one window; closed, the secret is gone, "reset secret" there (that key only); key and prompt stay copyable.
- Pinegrap AI was inefficient in long tests.

## What is there today?
- Claude (`ws_claude_settings_card()`): Create, the one-time window and Reset secret (step 1); a hand-made key is picked under Use another application; the prompt (`ws_claude_routine_prompt()`) holds no credential.
- Pinegrap AI (`ws_ai_settings_card()`): Create makes a row without a secret; it runs in the site, the row is identity (`<@app:N>`), read filter (`ws_ai_readable_types()`), poster (`ws_ai_owner()`).
- Links: `config.ws_claude_app_id`, `config.ws_ai_app_id`; role 0 saves; role-3 settings holders see the cards.
- api_settings.php: through the helpers (api/application-access-keys.md); Delete removes the row; nothing sets 'revoked'.

## Slices
- Done: 1 #1462, 2 #1523, 3 #1726, 5 #2733.
4. Pinegrap AI measurements (question 5).

## Which questions are answered? (owner, 2026-10-24)
5. Pinegrap AI: (a) measure first, (b) change limits → a; call limit 500 s since 2026-11-03 (workspace/workspace-ai-call-timeout.md).

## What could explain "Pinegrap AI worked inefficiently"? (open)
- Not the key. Nobody picks the model (`ws_ai_model()`: the first GET /models lists); `WS_AI_MAX_STEPS` 8; past `WS_AI_BUDGET` 20 s the next call waits for an open screen or hourly job; timeout 500 s; `max_tokens` 3000; 20-30 context messages; `ws_ai_tools()`, `ws_ai_system_prompt()` size.
- Measure `ws_ai_requests` (agent `ai`): `ai_steps`, `ai_attempts`, `claimed_at` to `answered_at`, `error`, `ai_state` size; tokens not kept. Briefing: workspace/workspace-claude-bootstrap.md.

## Where to start?
- Slice 4 (waits on question 5's measures). The sheet shows `ws_claude_routine_prompt()`, a bootstrap since #2736 (workspace/workspace-claude-bootstrap.md); the config link fits plans/agent-channel.md (step A6).
