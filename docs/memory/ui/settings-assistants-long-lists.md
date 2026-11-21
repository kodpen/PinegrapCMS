---
name: settings-assistants-long-lists
description: Site Settings › Assistants and agents, the long lists (AT6): MCP servers, Workspace agents and Canvas templates show at most five rows, then "Show all N" and the part's drawer
---

## What does a list show in its opened row?
- MCP servers, Workspace agents and Canvas templates show five rows at most, then one quiet small button "Show all N" (`ws_assistants_show_all_html()`). With five or fewer: every row in the list's order, no button, no drawer.
- With more, a row that needs something comes first, whatever its place: a rose or amber pill (`ws_assistants_list_asks()`), on an agent also a door warning, on a server also a form a check refused (its drawer is pressed again from the pane, `data-ws-mcp-reopen`).
- The cut is one pure function, `ws_assistants_list_cut($asks)` in `includes/workspace/assistants_fold.php`: it returns the keys to show.
- Why: architect (2026-11-21), from AT4's check (#10918): the three lists had no cap, against the category's rule that an opened row ends above the dialog's foot (`screen-composition-rules.md`).

## What does "Show all N" open?
- The part's drawer, `pgset-ws-mcp-all`, `pgset-ws-agents-all`, `pgset-ws-canvas-templates-all` (`ws_settings_assistants_all_drawer()`, titled by the row's name), with every row and the actions it has in the pane. Added to `$pg_settings_modals` by `includes/settings/workspace_assistants.php` only when the card hands back rows through its `&$all` argument.
- A server's Tools and settings and an agent's Edit open their own drawer over it; inside the dialog the helper renames the triggers to `data-pgdrawer="open"`, as the mailbox drawers do.
- The drawer's copies carry no `pgset-ws-mcp-row-<id>` / `pgset-ws-agent-row-<id>` id (`ws_agents_settings_row_html(..., false)`).

## How do the templates' switches post twice without harm?
- A template's row is a look A switch (`ws_canvas_templates_settings_row_html()`), name `ws_canvas_template[]` as before. The drawer's copy has the id `ws_canvas_template_all_<id>` and `form=` the settings form; `ws_canvas_templates_settings_script()` keeps a template's two switches alike, so an unticked one is not posted ticked by its twin. The drawer's foot holds Save for an administrator.

## What is not changed?
- Field names, button names and values, the save and the drawer ids of the servers and agents (`workspace/workspace-settings-row-names.md`).
- The rows' content is the cards' own: `workspace/workspace-mcp-settings-dialog.md`, `workspace/workspace-agent-settings.md`, `workspace/agent-canvas-templates.md`.

## What does a test pin?
- `tests/settings_assistants_list_cut_test.php`: six rows show five and "Show all 6"; five show no button; a rose row at place eight is shown first; the drawers' ids and renamed triggers; the templates' twin switches; the three cards use the one cut.

Related: docs/memory/ui/settings-fold-rows.md, docs/memory/ui/settings-assistants-help-lines.md, docs/memory/ui/screen-composition-rules.md
