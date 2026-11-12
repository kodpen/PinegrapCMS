---
name: agent-token-limit
description: A model assistant's monthly token limit (ws_agents.token_limit): set on its form, checked before each step, the request fails with a sentence naming the limit; the card shows used of limit
---

## Where is it?
- Column `ws_agents.token_limit` INT UNSIGNED, 0 for none (step 11.0.182, #8482). Helpers in `includes/workspace/agent_activity.php`; tests in `tests/workspace_agent_activity_test.php`.
- It lives apart from `ws_agent_shape()` / `ws_agent_save_check()` (`agents.php`): read by `ws_agent_token_limit()`, written by `ws_agent_token_limit_save()` after `ws_agent_save()` in `ws_agents_settings_write()` (both screens), and only for a model agent; checked with the form in `ws_agents_settings_check()`.

## How is it set?
- The "Monthly token limit" field of a model assistant's form (and of a new agent's, used only when its kind is model). `ws_agent_token_limit_check()`: empty is 0, spaces, dots and commas are dropped, a whole number up to 4,294,967,295; anything else refuses the save.

## When does it refuse?
- `ws_model_agent_run()` asks `ws_agent_token_refusal()` before each step of a request: once this calendar month's tokens reach the limit (`ws_agent_token_limit_reached()`, reaching is enough), the request fails through `ws_ai_finish_fail()` with "{name} has used its monthly limit of {n} tokens; it answers again next month or when the limit is raised." Waiting requests fail the same way on their turn, so nobody waits for a month.
- The month: requests created since the first second of the month in the site's time zone (`ws_agent_month_start()`), summing `tokens_in + tokens_out` (`ws_agent_month_tokens()`). A step already sent may pass the limit by its own tokens: the check is before a step, not inside the provider call.
- Pinegrap AI has no such limit: it is paid from the licence's quota. Claude and app agents spend no site key and count no tokens.

## What does the card show?
- On a model assistant's activity block, after the 7/30-day token line: "This month: {used} of {limit} tokens", only when a limit is set.

## Why?
- A model assistant spends the site's own provider key; the hourly brake alone lets one agent wake about 21,600 times a month (`workflow/decisions-made-schema-14.md`).

## What is not done?
- Not tried on a database or a browser: the month's SUM, the save and the refusal under a message.
