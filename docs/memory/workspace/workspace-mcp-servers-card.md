---
name: workspace-mcp-servers-card
description: Workspace settings "MCP servers" card (mcp.php): read-only tools on at the first read, the open tools panel, the warned declare button, the form kept after an error, Pinegrap AI failing
---

## Which tools are on after a server is added?
- Symptom "no tool on, @alias missing": every tool started off (`tool_rules` `{}`), so the @ picker (`ws_mcp_boot()`), the form run and agent mode skipped the server, and the switches sat in the closed "Tools and settings" collapse.
- Owner decision (2026-10-24): the first read turns on every tool the server marks `readOnlyHint: true` (rule `auto`). `ws_mcp_refresh_tools()` (save, refresh button, OAuth return) asks `ws_mcp_first_read()` (no tool known, no rule) and `ws_mcp_rules_after_read()`; the notice and the Site Log say how many. A server read before keeps its rules, even all off.
- Tools the server says nothing about ("Not said": DeepWiki, GitMCP) stay off. "Declare the "Not said" ones read-only" is a submit button of the tools form (`mcp_declare_all`) behind a `confirm()`: everybody who may ask the alias works with the site's one account there (OAuth: their own, else the site's). It saves the form with each off one as `auto_declared` (`ws_mcp_rules_declare_all()`); one set to "Ask before each call" keeps asking. Tools that write are unchanged: OAuth only, approved per call.
- "Every tool that reads only" is shown only when the server marks one so.

## When is "Tools and settings" drawn open?
- For an administrator, when the server is on, has tools and none is on: `show` and `aria-expanded="true"`, server side. The tools panel then says the alias is not offered after @.

## What comes back after a refused add or change?
- Symptom "form emptied after an error": the screen redirects, and the form was filled from the row only.
- On a problem `ws_mcp_settings_save()` calls `ws_mcp_settings_keep()`: the liveform keeps `ws_mcp_settings_keep_fields()` (alias, name, URL, sign-in kind, header name, user name, client ID), the reason and the form (`mcp_kept_for`: `new` or the id). Never a token, password, header value or client secret.
- workspace_settings.php passes `$liveform` to `ws_mcp_settings_card()`, which opens that form (`#ws-mcp-new` or `#ws-mcp-edit-<id>`) with the values and the reason; `remove_form()` empties them. "Kept: leave empty" follows the stored kind, not the typed one.

## What does the card say while Pinegrap AI fails?
- Symptom "Pinegrap AI gateway 502": the AI card stays Ready (`ws_ai_missing()` is empty), every @alias waits and fails after `WS_AI_MAX_ATTEMPTS`. Queued requests read "Waiting: Pinegrap AI could not be reached. Next try: HH:MM"; the one being tried reads "Pinegrap AI is working on it". The gateway is Kodpen's to fix.
- Not ready: the "not connected yet" alert. Ready with `ws_ai_config()['error']` set: an alert that the aliases wait, with the error under it. A good answer or a successful Try clears it.

## When is the form-run link posted?
- Owner decision (2026-10-24): when Pinegrap AI is not set up (`ws_ai_after_send()`), and once at a request's first hold: `ws_ai_step()` calls `ws_ai_hold_form_hint()` when no model is listed or the answer is 0/5xx. `ws_ai_form_hint_due()`: `mcp_server_id` set, a channel, no note or task, `ai_steps` and `ai_attempts` 0 as the call began, so retries stay quiet.
- The line is "Pinegrap AI could not be reached, so the request waits…" plus `ws_mcp_form_hint()`; nothing when no tool runs with a form. A request queued during another's hold is told at its own first failed call.

## How is it checked?
- tests/workspace_mcp_test.php: first read, declare, kept fields, link due.
- Run once (2026-10-24) through a CLI harness: the real functions on a bare MariaDB, DeepWiki (no hints), Microsoft Learn (read-only) and the live 502 gateway. No browser was used.
