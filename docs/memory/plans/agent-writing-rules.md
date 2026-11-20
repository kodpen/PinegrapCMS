---
name: agent-writing-rules
description: Plan (architect 2026-11-20, from the owner's question): the agent instructions tell an agent how a text is written so the channel draws it (lists, tables, code, bold); one step, AW1
priority: 7
---

## What is asked?
- Owner (2026-11-20, in the architect's window): "kanalda sen doğru tekniklerle konuşmana rağmen diğer ajanlar araçları kullanmıyor sebebi nedir?", with a coordinator's column list drawn as plain lines.
- What the architect found: the instructions an agent reads (`GET /workspace/agent-instructions`) say only "short and concrete" and name the `<#message:ID>` tag; no line says which marks the Workspace draws. The coordinator had written a list, but a line followed it in the same paragraph, so by the list rule it stayed text (`workspace/workspace-text-lists.md`).

## What did the architect decide? (2026-11-20)
- The instructions gain a short part on writing; the list rule itself is not loosened, as texts stored before lists were drawn must keep their look.
- An agent takes rules only from its instructions and the channel's memory, never from a channel message, so the text is the place.

## What is built?
- (W1) A part of at most 800 characters in the instructions' text: bold for a decision or a name to be found; code marks for a file, a function, a column, a command; a list as lines that run to the paragraph's end, with an empty line before the next sentence; columns, options side by side and figures as a table, with an empty line after it; a quote; a message named by its tag. Only marks the renderer draws are named (`includes/workspace/render.php`); no headings in a message.
- (W2) The brief is not changed: the instructions' version changes, and a session reads them again at its next request, as today.
- (W3) A pull request's reference is named in the part only if the renderer draws it as a link; the worker checks and says which form it found.

## Which steps are there?
- AW1 (S): the part in `includes/workspace/agent_instructions.php`.
  - Tests, red first: every example the part gives is passed through the renderer and draws as the part says (a list, a table, code, bold); the part stays under its length; the version differs from the one before.
  - Memory: the file of `workspace/` that describes the instructions' text corrected; this plan deleted in the same PR.
  - Seen on dev: an agent's answer written by the part's examples, drawn in the agent channel.

## What binds it?
- `includes/workspace/agent_instructions.php` is also entered by AA7 of `plans/app-access-redraw-steps.md`: AW1 goes first.
- The instructions stay in English and say nothing a person's site would not want every agent told.
