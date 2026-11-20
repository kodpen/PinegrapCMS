---
name: ai-guide-steps-2
description: Plan ai-guide, steps AG8 to AG14 and the order of all fourteen: the Design and Content presets, the size budget, the description diet by area, hints on refusals, the refused calls report, close-out
---

## Which steps are there?
- AG8 (S) any time, done (#10709): presets Design and Content. `includes/oauth/rules.php` (`pg_oauth_consent_presets()`), the consent screen's links, `tests/oauth_consent_presets_test.php`. Feature: G15.
- AG9 (S) after AG7: the budget. `includes/mcp/tools.php` and `includes/api/openapi.php` (the `details` key, read by the document only), a test with today's tools over 600 characters or 4,096 bytes as a list that may only shrink (209 and 13 on 2026-11-20). Feature: G16.
- AG10 (M) after AG9 and AG4: the Workspace's diet (95 descriptions, 7 tools over 4 KB). Long text into `details` or a section of `workspace`; cut in two PRs by route file when one passes the size limit of a PR.
- AG11 (S) after AG9 and AG4: the ERP's diet (25 descriptions).
- AG12 (M) after AG9, AG2 and AG3: the diet of the rest (design 10, store 2, the others 77; 6 tools over 4 KB).
  - In each diet step: nothing a right call needs is dropped; the worker takes each tool off the test's list as it fits; the PR body gives the tool list's bytes before and after.
- AG13 (M) after AG2: hints. New `includes/fn/agent_guide_hints.php` (the registry by error code, each naming a section that exists: a test), `includes/api/response.php` (`api_fail()` reads it), the designer's refusal codes made distinct where two cases share one. Then the report: `api_settings.php` and a reader for `GET /reports/api-refusals`, one indexed read of `api_request_log`. Features: G18, G19. Cut in two PRs (hints, then the report) if it passes the size limit.
- AG14 (S) close-out, after all: every G number checked against the code with its PR; `api/agent-guide-overview.md` and its files written; `api/api-mcp-server.md`, `api/api-oauth-consent-presets.md`, `api/api-plugin-scopes.md` (the money mark is now read at run time) and `plans/claude-plugin-bundle-steps.md` (CB3 names `guide_read`) updated; the plan's files deleted; the changelog `[NEW]` line named in its PR; the tool list measured again and written into the overview.

## Which steps run side by side?
- After AG1: AG2, AG3, AG4, AG5 and AG7 side by side: each topic is its own file. AG8 needs nothing.
- `includes/mcp/tools.php` is entered by AG1, AG7 and AG9 in that order, each after the one before it has landed.
- A diet step edits route rows in files other plans enter: the coordinator gives it out away from a file an open PR holds.

## What does each step's PR carry?
- `Decision: channel message 10442`; no migration step; what was measured (bytes) where a step changes the tool list or the instructions.
