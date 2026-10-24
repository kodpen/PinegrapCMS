---
name: workspace-assistant-key-setup
description: Plan (2026-10-23, step 1 in PR #1462, answers 1-4 given): Create on the Claude and Pinegrap AI cards makes the linked key, shows key, secret and prompt once; a reset for that key
---

## What is asked? (owner, 2026-10-23)
- "Create" on the Claude or Pinegrap AI card makes its Application Access key (every read, Workspace and Tasks writes) and shows prompt, key and secret in one window; closed, the secret is gone, "reset secret" there (that key only); key and prompt stay copyable.
- Pinegrap AI was inefficient in long tests.

## What is there today?
- Claude (`ws_claude_settings_card()`, `ws_claude_settings_post()`): a key made by hand on api_settings.php (guide step `app`, `ws_claude_setup_hint()`), picked in a select; the prompt (`ws_claude_routine_prompt()`) holds no credential.
- Pinegrap AI (`ws_ai_settings_card()`): same select; it runs in the site, the row is identity (`<@app:N>`), read filter (`ws_ai_readable_types()`), poster (`ws_ai_owner()`); no prompt, no secret used.
- Links: `config.ws_claude_app_id`, `config.ws_ai_app_id`; role 0 saves; role-3 settings holders see the cards.
- api_settings.php: hashed secret, one-time sheet via the session, removed on Close; rotation keeps the old secret 24 h. Delete removes the row; nothing sets 'revoked'.

## What is proposed? (not approved yet)
- includes/api/keys.php: `api_app_create()`, `api_app_rotate_secret($id, $grace)` (proposed), with the screen's audit and log.
- Scopes (proposed `api_assistant_scopes()`, pure): per group its read, or `workspace:write` / `tasks:write`; no other write; cut to `api_owner_scopes()` of the clicking admin (owner); each call capped by `api_effective_scopes()`.
- No schema: the id goes in the config link; reset reads it there, never the post.
- Sheet once after redirect (session, never URL or log): key, secret, domain, prompt to copy; "I have copied it" unlocks Close. Then role 0 sees key, prompt (copy), hint, "Reset secret".
- Reset: linked 'server' key only, grace 0 (old secrets fail at once), audit, log, shown once.
- POSTs: role 0, `validate_token_field()`; `lang()` labels.

## Slices
1. No schema. Claude: helpers, scopes, Create, sheet, reset, guide and hint; the select stays in the Save form under "Use another application".
2. No schema. Pinegrap AI: Create (question 3), guide.
3. No schema. api_settings.php uses the helpers, same behaviour.
4. Pinegrap AI measurements (question 5).

## Which questions are answered? (owner, 2026-10-24)
1. Reads: (a) all, (b) all but `account:read`, `key_codes:read`, `gift_cards:read`, `site_log:read`, `system:read` → b. Claude's GET /orders etc. run as the key owner, so a public-channel asker reads what the admin reads (Pinegrap AI reads as the asker). Answer: b.
2. Claude's prompt also uses `design:write` (proposals; the editor needs it, `pg_design_ai_scopes()`) and `translations:write` (job queue), both also live writes: (a) left out, the card says what fails, (b) added. Answer: a.
3. Pinegrap AI: (a) no secret (cannot sign in), no reset, (b) a secret, a REST/MCP door nothing uses. Answer: a.
4. A hand-linked key: (a) kept, reset offered (its other users stop too), Create only with no row linked, (b) replaced. Answer: a.
Open: 5. Pinegrap AI: (a) measure first, (b) change limits → a; ai.pinegrap.com answered 502 on 2026-10-23/24.

## What could explain "Pinegrap AI worked inefficiently"? (open)
- Not the key. Nobody picks the model (`ws_ai_model()`: the first GET /models lists); `WS_AI_MAX_STEPS` 8; past `WS_AI_BUDGET` 20 s the next call waits for an open screen or hourly job; timeout 55 s; `max_tokens` 3000; 20-30 context messages; `ws_ai_tools()`, `ws_ai_system_prompt()` size.
- Measure `ws_ai_requests` (agent `ai`): `ai_steps`, `ai_attempts`, `claimed_at` to `answered_at`, `error`, `ai_state` size; tokens not kept. Briefing: plans/ai-agent-prompts.md.

## Where to start?
- Slice 1. The sheet shows `ws_claude_routine_prompt()`, a bootstrap after plans/ai-agent-prompts.md step 3; the config link fits plans/workspace-agent-handles.md.
