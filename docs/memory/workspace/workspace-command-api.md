---
name: workspace-command-api
description: / commands over the API: GET /workspace/commands lists the registry (#1991); POST /workspace/commands/run runs one as the composer does (#3823); dry run previews; no MCP tool
---

## What does GET /workspace/commands answer?
- Route `workspace.commands.list` in includes/workspace/api.php; handler and presenters in includes/workspace/api_commands.php. workspace:read, no params.
- `ws_commands($viewer, true)` of the key's owner in registry order (workspace-command-registry.md): a command the owner may not use is left out (its gate: /price, /stock, /tracking, /create, /product without the record's right; /claude, /ai until set up; /schedule, /timer, /recap…). No channel is given, so a channel-only command and an agent's command are listed too; a channel's flows are not.
- Each row is a `WorkspaceCommand`: key, names, name, label, description, usage, example, args, live, app_id (0 built-in), app (null built-in), shadowed (workspace-command-registry.md). Group, where and /create's kind variants are not in it. Applications' commands come last, with their defined args (workspace-app-commands.md).
- Each arg a `WorkspaceCommandArgument`: name, kind, label, optional, suggest, multiple, anywhere; an empty `suggest` is `null`. Texts in the site's language (`ws_command_turkish()`).

## What does POST /workspace/commands/run do? (PR #3823, decided #3801)
- Route `workspace.commands.run`, workspace:write, `'dry_run' => true`, `'mcp' => false`. Body: `channel_id`, `text`, and the composer's options `confirm`, `force`, `assignees`, `from`, `decision`, `on_sale`, `parent_id` (`ws_api_command_options()` reads them as ws_send in actions.php does).
- Order in `ws_api_commands_run()`: `ws_api_own_words_only()` (only the team devices session: a command writes in the person's name, and an app key running /price would write a product with only workspace:write); channel readable (404) and postable (403); `ws_api_command_text()` applies `ws_claude_command_body()` / `ws_ai_command_body()` first, so /claude and /ai are messages; no command (`ws_api_command_kind()` 'none') → 400 `not_a_command`, never posted; then the dry run; then `ws_command_run()` inside `ws_api_as_owner()`.
- Answer `{command: …}`: what `ws_command_run()` returns (done, ephemeral, error, or a card or form of `ws_api_command_preview_types()`), 200 also for `error`, as the composer receives it. Each effect's right is checked inside the run, unchanged.
- The OpenAPI description says the contract is "what the web composer accepts": the parser may change between releases; a stable contract is the typed endpoints.

## What does a dry run of a command answer?
- `detail: {command, preview}`. `ws_api_command_kind()` reads the entry's `api` before anything runs: 'preview' (/task, /price, /stock, /tracking, /search, /flow; /create and /product on one line; /poll with fewer than two options; /remind with no time, /approval without title or people, /update without health, /checkin and /memory without text: their forms) is run with `confirm` and `force` false.
- `ws_api_command_preview()` makes that run; its answer kept only if of `ws_api_command_preview_types()` or `error`. Everything else ('run': /done, /decision, a bulk card, an app's command, a mistyped /word) gets `preview: null` and is not run. A doubtful case must be 'run'.

## And an application's answer?
- `POST /workspace/commands/answer` is an application's answer to its command's writer, not a command run: `workspace-app-answers.md`.

## Why no MCP tool?
- `'mcp' => false` on both: a model gains nothing from the picker's list or the composer's parser; it calls the endpoints a command would (tasks, plan items, scheduled messages, changes).

## Tests?
- tests/workspace_commands_test.php: the list route, presenters matching their schemas, registry order. tests/workspace_command_run_test.php: the run route's declaration, `not_a_command`, which texts preview, the dry run never running a writer (a recording runner), the options, the assistant bodies first.
