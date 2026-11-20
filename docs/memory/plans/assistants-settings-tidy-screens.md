---
name: assistants-settings-tidy-screens
description: Plan assistants-settings-tidy, Claude's opened row part by part (step AT2b): what stays, what goes into the fold and the drawers, what a test pins; the other rows' check
---

## Why this file?
- Owner (2026-11-20, with a picture of Claude's row on dev): "claude ekranı en az 4 modal ekran boyu kaydırma sonra bitiyor. çizimindeki modernizim ve pratiklik tamamen atlanmış".
- The drawing (`assistants-settings-tidy/assistants.html`, board A) shows the opened row as one line, four fields and two buttons. The plan's T3 named only the guide; AT1 and AT2 (#10860) moved the guide and left every other part where it was. This file places each part.

## Which step builds it?
- AT2b (M), after AT2 #10860 lands, before AT4: `ws_claude_settings_card()` in `includes/workspace/claude.php`. Field names, button names and values and the save do not change (T6). Parts from the Design examples screen, section in brackets.

## What does the opened row hold, in this order?
- One column; the right column goes.
- Notices, only when true, one line each [status]: the site is not on HTTPS; the routine still runs the old long prompt (with "Show the new prompt", which opens the set-up drawer at that step); the last error; "Requests wait until …".
- The switch "Claude can be asked in the channels" with its one line [fields: switch].
- "The routine's address", full width. "The routine's token", full width, with one line of help ("Stored encrypted and never shown again.") and "Remove the stored token" on that same line.
- "Daily summary": Hour and Channel side by side, one line of help.
- One row of quiet small buttons [buttons]: "Try the connection", "Set up on claude.ai" (reads "Finish the set-up" while something is missing), "The latest requests"; at the right a grey line, "Last request 14:05 · Claude answered".
- Last, a fold "Application and key" (`.pg-gather-toggle` [rows]), shut while an application is chosen: its name with a link to Application Access, Key with Copy, the secret's last four, "Reset secret" (quiet small, its warning in the confirm), "Use another application" with its select and what it may read. With no application the fold is open and holds "Create Claude's key" and one line.

## What leaves the row?
- "The prompt for the routine": only in the set-up drawer's step that asks for it.
- "Still missing": the row's head says the first missing thing already (AT1); the whole list stands at the top of the set-up drawer.
- "The latest requests" and "Version of the instructions the site gives the routine": a new drawer "The latest requests" (`pg_settings_drawer()`, id `pgset-ws-claude-requests`): the version as one line with its help, then the ten requests as the rows they are today [rows].
- Every sentence past one line of help: behind "How does this work?" in the set-up drawer.

## What must be true? (a unit test pins each line)
- The card in the dialog has no second column and, outside the fold and the drawers, exactly: one switch, two text fields, one checkbox, two selects, three buttons; no copy field, no list of requests.
- No help text in the row is longer than 110 characters in English.
- The fold holds the key, the secret's hint, `claude_key_reset` and the `app_id` select.
- The posted field names and the button names and values equal those before the step.
- Seen on dev after landing, by the test coordinator: at the dialog's large size the opened row ends above the dialog's foot with no notice showing; at 390 px it is one column.

## What about the other six rows?
- AT3 cuts their help. AT4 checks each against the same lines: one column, one line of help a field, no paragraph in the pane, a list of at most five rows then "Show all N" in the part's drawer. A row that fails is named to the architect before AT4 closes.

Related: docs/memory/plans/assistants-settings-tidy.md, docs/memory/ui/settings-fold-rows.md, docs/memory/workflow/screen-description-checks.md
