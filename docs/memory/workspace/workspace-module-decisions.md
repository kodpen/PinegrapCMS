---
name: workspace-module-decisions
description: Binding decisions of the Workspace module (channels, tasks, plan board): rights not roles, private channel audit, chat bubble kept apart, multiple assignees, customer context, API scope ceiling
---

## What is the Workspace and where is its code?
- Team channels, tasks, plan board, calendar, notes; internal name `workspace`, prefix `ws_`, UI name "Çalışma Alanı". Code `includes/workspace/` (one gate constant per file), screens `workspace*.php`, JS `assets/js/workspace*.js` (bundled: `php tools/build_js.php`).
- Built from 2026.4.4 (labels 4.80–4.89); open-version workspace labels 10.80–10.89 and 10.110–10.119.
- Goal: conversation → decision → task → calendar in one place, tied to the site's own records (orders, invoices, products, contacts, files, pages).

## Who may enter? (K3, K10, K14, K16)
- Rights, not new role numbers (100+ `== 3` and 300+ `< 3` checks would silently misread a role 4). Role 3 stays "User"; `TINYINT` columns on `user`: `manage_workspace` (gate), `manage_workspace_assign`, `manage_workspace_board`, `manage_workspace_settings`. Roles 0–2 are members by default, `ws_profiles.excluded` opts one out.
- A new right column goes into all five role-3 ladder files (`core/permission-columns.md`) and the `pg_user_has_ws_columns()` probe, or a user with only that right loses access.
- `user` also holds site members (customers) as role 3: no `ws_` screen, endpoint, push, search hit or file may reach them. First acceptance test.
- Department lead is a flag (`is_lead`), not a right: assign and board rights inside their own department only. A person can be in several departments, one `is_primary` (K6).
- Without `manage_workspace_assign` a member gives tasks only to themselves and departments they lead.

## How do channels behave? (K4, K5, K9, K11, K13)
- `kind` is `public` or `private` only. Private → public is allowed (with a confirmation showing message and file counts); public → private is not.
- Admins do not see private channel content; they can open it leaving a trace (system message in the channel + `log_activity`).
- The chat bubble (`chat.php`, one-to-one and visitor chat) stays separate; no DM type in the Workspace, no migration of `chat_*`. K15: two team members (`manage_workspace`) may chat in the bubble; the role-3 ↔ role-3 ban stays for site members (`pg_chat_can_pair()`).
- Customer context of a channel is an address book contact (`contacts.id`).
- Messages are kept indefinitely; a leaver's messages stay under "former member".
- Private channel files: `ws-` names / channel folders, served by `get_file.php` only to channel readers.

## How do tasks work? (K7, K8, K12)
- Several equal assignees (`ws_task_assignees`), owner's decision.
- Conflicts warn and offer choices (assign anyway, least-loaded person in the department, move the date); hard conflicts (leave, overlapping timed items) can be overridden only by board right holders, department leads and admins. A task without an estimate counts as 60 minutes (setting).
- Threads arrived in phase 2; phase 1 had quoted replies (`parent_id`).

## Which access rules are central?
- One set of access functions (`ws_can_read_channel()` and friends) is called by screens, API, push, search, record cards, files. A record tag never leaks: a viewer without the record's right sees only "Order #1045", and mentions in channels they cannot read are not even counted.
- Tags are stored as tokens (`<@user:12>`, `<#order:1045>`), drawn per viewer; `ws_refs` gives the reverse lookup.
- Sync is polling (`ws_sync`), no WebSocket or SSE (shared hosting, IIS FastCGI).

## What must an API change remember?
- Every feature ships with its API (`includes/workspace/api.php`, module seam in `includes/api/modules.php`).
- A new scope must also enter the owner ceiling `ws_owner_scopes()`, or the endpoint answers 403 (happened three times). `/workspace/plan-items` and `/workspace/plan` use `tasks:read/write`.
- Apps see only channels they are members of; only the owner adds an app to a private channel.
