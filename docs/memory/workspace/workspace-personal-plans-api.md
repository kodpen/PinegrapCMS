---
name: workspace-personal-plans-api
description: A person's own plans in the external API and MCP (PC8): /workspace/personal-plans, scope workspace:personal_plans, only the person's own device or OAuth connection, own plans only
---

## Which routes are there?
- `includes/workspace/api_personal_plans.php`: GET /workspace/personal-plans (`from`, `to` as YYYY-MM-DD; today and 30 days on by default, at most 366 days), GET/POST(PATCH) /workspace/personal-plans/{id}, POST /workspace/personal-plans, DELETE and POST …/{id}/delete. Object `WorkspacePersonalPlan`, what `ws_personal_plan_for_reader()` gives the owner.
- Fields as the drawer's (`ws_personal_plan_clean()`): title, note, start, end, all_day, start_time, end_time, remind (minutes as text, '' for none), visibility, people. An update lays the fields sent over the plan (`ws_api_personal_plan_merge()`, pure).
- Repeating plans (PC11b): repeat, repeat_every, repeat_end, repeat_until, repeat_count are written as the drawer's (`ws_api_personal_plan_check()` runs `ws_personal_plan_repeat_clean()` for the dry run); an update that sends no rule keeps it. The list gives a series once with its rule and `times` (day, start, end, start_time, end_time) inside from..to, `ws_api_personal_plan_times()` (pure), skipped days out; a plan that does not repeat has its one time.
- `mode` (one, following) and `day` (a time's day) on update (body), DELETE (query) and POST …/delete (body), as "This one" / "This and the following": `ws_api_personal_plan_time_asked()`; an update with them lays the fields sent over that time; a day with no time, or a mode on a plan that does not repeat, is 422 `day`/`mode`. Without them the whole series.
- Delete and its POST twin carry `'mcp' => false` (cannot be undone); list, get, create and update are tools. Every write is rehearsable: create and update run `ws_personal_plan_save_check()` (personal_plans.php, shared with the save: people with nobody on the team, a roadmap given a rule, the plan limit) before `api_dry_run_stop()`; before #9667 the dry run answered success where the save then answered 422.

## Who reaches a plan?
- One scope for reading and writing, `workspace:personal_plans` (group `personal_plans`, "My plans" on Application Access, no read half), in `ws_owner_scopes()` for every team member; the census's `workspace.personal_plans` in the form of `workspace:commands` and `workspace:mail_send`.
- `ws_api_personal_plans_caller()` calls `ws_api_person_only()` first: a team devices session or an OAuth connection the person approved. An application key gets 403 whatever its scopes, so an integration, a channel agent (app, Claude's routine; a model agent runs on the site and has no tool for it), Claude's key and Pinegrap AI never read a plan.
- Only the caller's own plans (`ws_personal_plan_can_edit()`): a plan shared with them is not listed and its id is 404; it stays on My calendar.
- With no read half it is in no assistant key (`api_assistant_scopes()`), no "All" on the consent screen and no plugin default (`api_plugin_scopes()`, not in `api_plugin_writes()`): the person ticks "My plans" themselves. The device and OAuth ceilings are the operator's: until they widen them, the routes answer 403.

## What does it never do?
- Name a plan table (the reads go through `personal_plans.php`), write a Site Log line, announce a webhook or a flow event. GET /workspace/my-day and the inbox do not carry a plan's words (`workspace-personal-plans.md`).

Test: `tests/workspace_personal_plans_api_test.php`.
