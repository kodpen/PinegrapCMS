---
name: channel-commands-cc10-args
description: Plan channel-commands, step CC10 proposal waiting for the architect: the ws_app_commands column for an application command's arguments and the definition's shape
---

## What is proposed?
- One column: `ws_app_commands.args TEXT NULL`, default NULL. NULL (or an empty list) is a command without a definition, which works as today. Added by one step of the open version with `install_add_column()`; existing rows stay NULL. Owner's yes: poll 97 (`workflow/decisions-made-schema-25.md`).
- TEXT, as the other JSON lists of 2026.11.0 (`ws_poll_options.links`, `ws_mail_items.draft_files`): kept off the row, no default needed, one definition is under 2 KB.
- `args` is not a reserved word (the table already has `usage_text` because USAGE is).

## What shape does a definition have?
- `POST /workspace/commands` takes `args`, a list of at most eight items in the order they are typed; stored as JSON in the registry's own shape (`ws_command_registry_arg()`): `name`, `kind`, `label`, `optional`, `suggest`, `multiple`, `anywhere`.
- `name`: `[a-z][a-z0-9_]{0,19}`, unique in the command; the key of the value in the event. `kind`: one of `ws_command_argument_kinds()`, else 422 `invalid_kind`. `label`: 1–40 characters, one line, shown in the strip (default the name). `optional`, `multiple`, `anywhere`: booleans, default false.
- `suggest` is not sent: the server sets it from the kind, as the built-in commands use it (person and people → people, department → people_departments, task → tasks, record → records, date → dates, estimate → estimates, priority → priorities, status → statuses, record_kind → kinds, health → healths, notify → notify; the rest none).
- A ninth item: 422 `too_many_args`. Any other bad item: 422 `invalid_args` with the item's index. The limit of twenty commands per application is unchanged.
- On a change of a registered command an omitted `args` keeps the definition and `[]` or null removes it. With a definition and no `usage`, the usage is written from the labels (`<label> [label]`).

## What does the application hear?
- `workspace.app.command` gains `values`, by argument name, only for a command with a definition; `text` and `body` stay. A person, department or task is its id, people a list of ids, a record `{type, id}`, a date `YYYY-MM-DD`, a time `HH:MM`, an estimate in minutes, an amount in minor units, a quantity an integer, text a string, options a list of strings; an optional argument not typed is null.
- A required argument that cannot be read: the writer is told which and nothing is sent.

## Then
- After the architect's yes: the confirmed column goes to `workflow/decisions-made-schema-25.md`, the code and tests of CC10 (`plans/channel-commands-steps-2.md`), memory in `workspace/workspace-app-commands.md`; this file is deleted.
