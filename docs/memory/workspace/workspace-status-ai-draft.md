---
name: workspace-status-ai-draft
description: The Pinegrap AI draft of a channel's status update (ws_status_ai, status.php): what the model reads, why it asks through pg_ai_chat() and not ws_ai_http()
---

## What does the draft read?
- `ws_status_ai_draft()` (`includes/workspace/status.php`): the channel's name, its figures (`ws_status_facts()`) and the latest check-in's question with up to 30 answers (600 characters each), as JSON data under the writer rules (`ws_status_ai_messages()`, `pg_ai_prompt_rules('writer')`). Only where `ws_status_ai_allowed()`: Pinegrap AI is set up and may be asked in the channel.

## Why through pg_ai_chat()?
- The draft called the gateway with `ws_ai_http()` itself. It was sent while the connection was on hold after a failed call (a 429 or a 5xx holds every caller for a minute), and its own 401/402/429/5xx answers were not recorded: no licence verdict stored, no hold set, so the @ai queue and "Write with AI" kept calling too.
- Now `pg_ai_chat()` makes the call: it refuses during a hold (`pg_ai_state()`), keeps the bookkeeping (`ws_ai_response_problem()`) and cleans the answer (`ws_ai_clean()`). A refusal is an empty draft; the form says it could not write one.
- Tests: `tests/workspace_status_test.php` (the call goes through `pg_ai_chat()`, the messages' shape).
