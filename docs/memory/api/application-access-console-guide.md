---
name: application-access-console-guide
description: The Claude Console guide of api_app_guides() (AA7b): a Claude Managed Agents agent working its channel requests over MCP as the application; its intro, steps and notes
---

## Where is the guide?
- `api_app_console_guide($at)` in `includes/api/app_guides.php`, id `console`, after One token. An integration with a secret only (`api_app_guide_fit()`: `kind` on OAuth, `no_secret` without a secret). Acts as the application.
- Shown on the edit drawer's Connect tab and in step 3 of New application under "Connecting another way?" (`application-access-drawer-guides.md`).

## What does it say first?
- Its `intro`, drawn above "Acts as:" by `api_app_guide_body_html()`: it follows Anthropic's Claude Managed Agents pages as read on 20 November 2026 and the product is in beta (`claude-console-agents.md`). Other guides have `intro` ''.

## Which steps?
- Make the application an agent (AI agent tab): its own MCP tools are offered only to an active agent's own key (`mcp-agent-own-tools.md`).
- The MCP server named pinegrap at the MCP address (`console_mcp`).
- The one token (`api-app-token.md`) as a vault's static bearer credential for the same MCP address.
- The site's host among the environment's allowed hosts (`console_host`, from the site address; no value without one).
- The start text as the system prompt (`console_start`; without the Workspace a line to switch it on).
- A scheduled run wakes it: each run reads the request list, takes what waits, answers and ends.

## What do the notes say?
- A call waits in the request queue until the next scheduled run.
- Sign-in (OAuth) is not offered: it acts as the person, never as the agent.
- Why a schedule: Owner (2026-11-20, poll 182): `workflow/decisions-made-workspace-43.md`. The site keeps no Anthropic key and calls no Anthropic address.

Tests: `tests/api_app_guides_test.php` (`test_api_app_guides_console`).
