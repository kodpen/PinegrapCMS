---
name: settings-assistants-help-lines
description: Site Settings › Assistants and agents, the help (AT3): one line per part with "How does this work?" opening a drawer of numbered steps; Add an agent's "Start from an application"
---

## What does each part say?
- Pinegrap AI, Workspace agents, MCP servers, Voice and Canvas templates each open with one line of help, then a ghost button "How does this work?" that opens the part's guide drawer `pgset-<key>-guide` (`ws-ai`, `ws-agents`, `ws-mcp`, `ws-voice`, `ws-canvas-templates`).
- Lines, drawer titles and steps: `includes/workspace/assistants_help.php` (`ws_assistants_help_line()`, `ws_assistants_guide_steps()`, pure but for `lang()`); the cards call `ws_assistants_help_html('<key>')` (Pinegrap AI and Voice: the line under the switch, `ws_assistants_help_button()`); `includes/settings/workspace_assistants.php` draws the drawers for the parts it shows. Test: `tests/settings_assistants_help_test.php` (one sentence, at most 130 characters, two steps or more).
- The drawer numbers its steps with Bootstrap's stepper as Claude's set-up drawer does (`stepper pg-stepper-cards`, each `stepper-item active` holding one `<div>`). A stepper item is a two-column grid: with the title, text and link as separate children, the text fell into the number's narrow column, a few letters a line (seen at 1440 and 390 px on #10864).
- Claude's row has its own set-up drawer (step AT2, `includes/workspace/claude.php`); the paragraph on what an application is stands there once.

## What moved out of the cards?
- Pinegrap AI: the "How to connect it" fold and the Licence paragraph; the switch's help is one plain line. Its right column went with AT5 (`settings-ai-voice-rows.md`).
- Agents: the intro paragraph; the empty list says "No agent yet". MCP servers: the "How it works" fold. Voice: the switch's three-sentence help. Canvas templates: the intro paragraph; the empty list says "No templates yet."
- Why: owner (2026-11-20) "kopya içerik gibi sürekli" (`workflow/decisions-made-panel-11.md`); one line a field or part (`ui/screen-composition-rules.md`).

## What does Add an agent offer?
- The drawer opens with "Start from an application", leading to Application Access (`api_settings.php`). It opens the list; the "Agent in a channel" starting point is under New application (#11072, `api/application-access-agent-tab.md`).

Related: docs/memory/ui/settings-fold-rows.md, docs/memory/ui/screen-composition-rules.md
