---
name: workspace-app-command-args
description: An application command's arguments (ws_app_commands.args, 11.0.246, PR #10322): the definition of POST /workspace/commands, suggest from the kind, values by name in workspace.app.command
---

## How does a command define them?
- `args` of `POST /workspace/commands`: at most 8 items (`WS_APP_COMMAND_ARGS_MAX`; a ninth is 422 `too_many_args`), kept in `ws_app_commands.args TEXT NULL` (11.0.246) as JSON in the registry's shape (`ws_command_registry_arg()`). Check: `ws_app_command_args_check()`.
- Item: `name` `[a-z][a-z0-9_]{0,19}`, unique; `kind` of `ws_command_argument_kinds()` (else 422 `invalid_kind`); `label` 1–40, one line, default the name; `optional`, `multiple`, `anywhere` booleans. Any other bad item 422 `invalid_args`, field `args.<index>`.
- `suggest` is never sent: `ws_app_command_arg_suggest()` sets it from the kind (person/people → people, department → people_departments, task → tasks, record → records, date → dates, estimate → estimates, priority → priorities, status → statuses, record_kind → kinds, health → healths, notify → notify; the rest none).
- On a change, `args` left out keeps the stored definition; null or `[]` removes it. A definition with no usage writes one from the labels (`<label> [label]`); a stored row with a definition and no usage (a later change that sent none) gets that line when read (`ws_app_command_entry()`, #10769).
- A stored value that cannot be resolved (bad JSON, not a list, an unknown kind, a bad item) reads as no definition (`ws_app_command_args_read()`), never an error for the writer.
- The entry's `args` carry the definition, so the composer's strip and argument popup (`commandArgument()`, `drawCommandStrip()` in workspace.js) work as for a built-in command; no JavaScript of its own.
- Decision: `workflow/decisions-made-schema-26.md` ("Which column keeps an application command's arguments?").

## What does the application hear?
- `values` in `workspace.app.command`, only for a command with a definition; `text` and `body` stay.
- `ws_app_command_values()`, by argument name: person, department, task an id; people a list of ids; record `{type, id}`; date `Y-m-d`; time `H:i`; estimate minutes; amount minor units; quantity an integer; text and flow a string; options a list (split on commas, else spaces); a multiple argument a list; an optional one not typed null.
- Read: tags go to the tagged kinds; the other typed kinds take the first word of their kind anywhere on the line, in definition order; the words left go to the text arguments in turn, the last taking the rest.
- A required argument not read: the writer is told which ("/{var:1} needs {var:2}. Nothing was sent to the application.") and nothing is sent.

## Tests?
- tests/workspace_app_commands_test.php: args check, unknown kind, ninth argument, unresolvable stored entry as none, values by name, values only with a definition, the limit of twenty unchanged.
- Not run on a real database or in the composer: the 11.0.246 step, the stored definition and the missing-argument answer on screen.
