---
name: workspace-assistant-key-setup
description: Plan (2026-10-23; steps 1-3 (#1462, #1523, #1726) and question 6 done): Create on the Claude and Pinegrap AI cards makes the linked key; Claude's shows key, secret, prompt once, with a reset
---

## What is asked? (owner, 2026-10-23)
- "Create" on the Claude or Pinegrap AI card makes its Application Access key (every read, Workspace and Tasks writes) and shows prompt, key and secret in one window; closed, the secret is gone, "reset secret" there (that key only); key and prompt stay copyable.
- Pinegrap AI was inefficient in long tests.

## What is there today?
- Claude (`ws_claude_settings_card()`): Create, the one-time window and Reset secret (step 1); a hand-made key is picked under Use another application; the prompt (`ws_claude_routine_prompt()`) holds no credential.
- Pinegrap AI (`ws_ai_settings_card()`): Create makes a row without a secret; it runs in the site, the row is identity (`<@app:N>`), read filter (`ws_ai_readable_types()`), poster (`ws_ai_owner()`).
- Links: `config.ws_claude_app_id`, `config.ws_ai_app_id`; role 0 saves; role-3 settings holders see the cards.
- api_settings.php: through the helpers (api/application-access-keys.md); Delete removes the row; nothing sets 'revoked'.

## What was proposed? (approved by answers 1-4)
- Built in steps 1-2 as workspace/workspace-assistant-keys.md says: helpers in includes/api/keys.php, scopes by code cut to the presser, no schema (id in the config link, reset reads it there, never the post), sheet once via the session, reset grace 0, role 0 POSTs with `validate_token_field()`.

## Slices
1. No schema. Claude: helpers, scopes, Create, sheet, reset, guide and hint; the select stays in the Save form under "Use another application".
2. PR #1523. No schema. Pinegrap AI: Create without a secret (answer 3a), guide; no rotation gives such a row a secret (`api_app_secret_can_rotate()`); see workspace/workspace-assistant-keys.md.
3. PR #1726. No schema. api_settings.php uses the helpers, same behaviour (keyless refusal included).
4. Pinegrap AI measurements (question 5).
5. PR #2733. No schema. Application Access locks a row whose owner outranks the editor (question 6).

## Which questions are answered? (owner, 2026-10-24)
1. Reads: (a) all, (b) all but `account:read`, `key_codes:read`, `gift_cards:read`, `site_log:read`, `system:read` → b. Claude's GET /orders etc. run as the key owner, so a public-channel asker reads what the admin reads (Pinegrap AI reads as the asker). Answer: b.
2. Claude's prompt also uses `design:write` (proposals; the editor needs it, `pg_design_ai_scopes()`) and `translations:write` (job queue), both also live writes: (a) left out, the card says what fails, (b) added. Answer: a.
3. Pinegrap AI: (a) no secret (cannot sign in), no reset, (b) a secret, a REST/MCP door nothing uses. Answer: a.
4. A hand-linked key: (a) kept, reset offered (its other users stop too), Create only with no row linked, (b) replaced. Answer: a.
Open: 5. Pinegrap AI: (a) measure first, (b) change limits → a; ai.pinegrap.com answered 502 on 2026-10-23/24.
6. Rows of a higher-ranked owner in Application Access. Decided (owner, 2026-10-28): refused (Designer 1 above Manager 2). Done in slice 5 (api/application-access-keys.md).

## What could explain "Pinegrap AI worked inefficiently"? (open)
- Not the key. Nobody picks the model (`ws_ai_model()`: the first GET /models lists); `WS_AI_MAX_STEPS` 8; past `WS_AI_BUDGET` 20 s the next call waits for an open screen or hourly job; timeout 55 s; `max_tokens` 3000; 20-30 context messages; `ws_ai_tools()`, `ws_ai_system_prompt()` size.
- Measure `ws_ai_requests` (agent `ai`): `ai_steps`, `ai_attempts`, `claimed_at` to `answered_at`, `error`, `ai_state` size; tokens not kept. Briefing: plans/ai-agent-prompts.md.

## Where to start?
- Slice 4 (waits on question 5). The sheet shows `ws_claude_routine_prompt()`, a bootstrap after plans/ai-agent-prompts.md step 3; the config link fits plans/workspace-agent-handles.md.
