---
name: assistants-settings-tidy-screens-2
description: Plan assistants-settings-tidy, steps AT5 and AT6: Pinegrap AI's and Voice's opened rows in one column part by part, and the lists capped at five rows with "Show all N"
---

## Why this file?
- AT4's check (#10918) found four misses against `assistants-settings-tidy-screens.md`: Pinegrap AI and Voice in two columns with paragraphs of help, and the lists of MCP servers, Workspace agents and canvas templates with no cap.
- Architect (2026-11-21, agent channel message 11395): two more steps, AT5 then AT6, one after the other on the same screen. Names, values and saves do not change (T6). Parts from Design examples, section in brackets.

## AT5 (M): what does Pinegrap AI's opened row hold, in this order?
- `ws_ai_settings_card()` in `includes/workspace/ai.php`. One column; the right column goes.
- Notices, only when true, one line each [status]: the licence's state when it is not valid, the last error.
- The switch "Pinegrap AI can be asked in the channels with @ai" with one line of help; the sentences on what is sent to the model go behind "How does this work?".
- One line of facts under it: the licence pill, "Model" with its code, the tokens used, as one meta line.
- One row of quiet small buttons [buttons]: "Try the connection", "The latest requests"; at the right a grey line with the last request, or "Nobody has asked Pinegrap AI yet."
- Last, a fold "Application" (`.pg-gather-toggle` [rows]), shut while an application is chosen: its name and number, one line ("It needs no secret: Pinegrap AI runs inside this site."), "Use another application" with its select and what it may read. With no application the fold is open and holds "Create Pinegrap AI's key" and one line.
- "Still missing": the row's head names the first; the whole list tops the "How does this work?" drawer.
- "The latest requests": a drawer of its own (`pg_settings_drawer()`, id `pgset-ws-ai-requests`), the rows as they are today.

## AT5: what does Voice's opened row hold?
- `ws_voice_settings_card()` in `includes/workspace/voice.php`: one column.
- "Write voice messages out as text": the switch with one line; Provider and Model side by side, "Custom address" under them only for the custom provider; the key full width with one line ("Stored encrypted and never shown again.") and "Remove the stored key" on that line.
- "Recordings are sent to" and "Spoken answers are made by": one meta line each under their part, never a column.
- "Assistants answer a voice message by voice": the switch with one line; its other sentences behind "How does this work?".
- "Still missing" as on Pinegrap AI.

## AT6 (M): how are the three lists capped?
- MCP servers (`ws_mcp_settings_card()`), Workspace agents and canvas templates (`ws_canvas_templates_settings_card()`): the row shows five rows at most, in the order the list has today, then one quiet small button "Show all N" [buttons].
- "Show all N" opens the part's drawer (`pg_settings_drawer()`, ids `pgset-ws-mcp-all`, `pgset-ws-agents-all`, `pgset-ws-canvas-templates-all`) with every row and the same actions a row has in the pane.
- With five rows or fewer there is no button and no drawer. A row that needs something (rose or amber pill) is among the five whatever its place: such rows come first.
- The cut is one pure function shared by the three lists.
- AT6 is the close-out too: the memory files of the three parts corrected, the plan's files deleted.

## What must be true? (a unit test pins each line)
- Neither card has a second column; no help text in a row is longer than 110 characters in English; no list of requests stands in a pane.
- The posted field names and the button names and values equal those before each step.
- A list of six shows five rows and "Show all 6"; a list of five shows no button; a row with a rose pill at place eight is shown.
- "Screen check" at 1440 and 390 px against these lines in each PR.

Related: docs/memory/plans/assistants-settings-tidy-screens.md, docs/memory/plans/assistants-settings-tidy.md, docs/memory/ui/settings-fold-rows.md
