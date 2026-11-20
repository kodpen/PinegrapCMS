---
name: assistants-settings-tidy
description: Plan (owner 2026-11-20, urgent, in the architect's window): Site Settings › Assistants and agents tidied: each part one folded row, Claude's long guide in its drawer, repeated help cut; AT1 to AT4
priority: 8
---

## What is asked?
- Owner (2026-11-20, in the architect's window): "Asistanlar ve ajanlar ayar sekmesi korkun durumda birsürü seçenek var kopya içerik gibi sürekli. mesela claude ai bağlama alanı uzunca scrolla sebep oluyor uzun çok. ardından bağdaştırıcılarda olan MCP sunucuları alanı geliyor. ayarları bir toparlayalım." Recorded in `workflow/decisions-made-panel-11.md`.

## What is there today?
- Seven cards one under another, each open in full (`includes/settings/workspace_assistants.php`): Claude, Agents, Pinegrap AI, Voice, MCP servers, Bulk changes, Canvas templates (`workspace/workspace-settings-overview.md`, `workspace-assistant-settings.md`, `workspace-agent-settings.md`, `workspace-mcp-settings-dialog.md`).
- Claude's card carries its whole set-up guide in the pane. Claude, Pinegrap AI and an agent each explain an application and offer to make one. Connections lists the MCP servers too, and its Manage leads here.

## What is built?
- (T1) The pane is a list of folded rows, one a part, in this order: Claude, Pinegrap AI, Agents, MCP servers, Voice, Canvas templates, Bulk changes. A row: icon tile, name, one line of state ("Ready · last run 14:05", "3 agents · 1 not seen", "2 servers · 1 asks for a sign-in"), a state pill, a chevron. One row is open at a time.
- (T2) A row opens by a click, and by itself for an anchor (`pg_settings_workspace_link()`), a search hit, a field the save marked, a drawer the save opens again and a part that needs attention. After a save the open row stays open (`ui/settings-modal-save-place.md`).
- (T3) Claude: the opened row holds the connection's fields and Test; the set-up on claude.ai moves into a drawer "Set up on claude.ai" as numbered steps, opened from one button, which reads "Finish the set-up" while Claude is not ready.
- (T4) One line of help a part; what more there is to say goes behind "How does this work?" in the part's drawer. The paragraph on what an application is stands once, on the first part that needs one, with a link to Application Access; "Create" buttons stay.
- (T5) MCP servers stay here, their one home: the row's line counts them, the opened row lists the servers as today. Nothing moves to Connections; its Manage opens this row.
- (T6) Field names, button names and values, drawer ids and what a save stores do not change (`workspace/workspace-settings-row-names.md`, `workspace-settings-card-saves.md`): the save module and the search index read them.
- (T7) Agents: "Add an agent" keeps its drawer and gains the line "Start from an application" leading to Application Access's starting point (`plans/app-access-redraw.md`, once AA5 has landed).

## Where are the steps?
- `assistants-settings-tidy-steps.md` (AT1 to AT4). Claim as `[plan:assistants-settings-tidy] <step> — <topic>`.

## What binds it?
- The dialog's contract (`ui/settings-card-save-rules.md`, `ui/settings-modal-drawer.md`): no form of its own in the pane, a standing form in a drawer, never a second dialog. Look A's recipes (`ui/look-new-parts.md`). No pictures (`workflow/look-pictures-none.md`): seen on dev.
- `backend.src.css` and `backend.src.js`: never two PRs in one at once (SZ1 first, then AT1).
- `Decision:` the owner's words in the architect's window, 2026-11-20.
