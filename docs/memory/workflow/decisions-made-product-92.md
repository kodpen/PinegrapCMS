---
name: decisions-made-product-92
description: Decisions list, product part 92 (2026-11-20 on): text written for an AI agent to read is English only in the panel, the API and MCP, never translated
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-product-91.md`.

## In which language is text written for an agent? (owner, 2026-11-20, in the architect's window)
- "panelde ve api mcp de ajanlara tarifler sadece ingilizce olsun, çeviri vs. yapılmasın. token tasarrufu ve doğru algılama için bu önemli".
- English only, on every site, whatever the site's or the panel's language: the descriptions and instructions an agent reads, in the panel, the API and MCP. None of it is translated.
- Why: fewer tokens, and a model reads English with fewer mistakes.
- Said "in case it was skipped": the guide and the tool descriptions were English already (`decisions-made-product-85.md`), the API's texts too (`api/api-change-rules.md`), the writing rules (`workspace/workspace-writing-rules.md`). It now binds every text of the kind, old and new.

## What does it cover?
- The AI guide and its doors, the agent instructions and the start text, a tool's and a field's description, the route summaries of `api_schema()`, a refusal's `hint`, the writing rules, the assistants' prompts.
- In code such a text is a plain English string: never through `lang()` or `_sdT()`, no key by `tools/tr_add.php`, no language file entry. Where the panel shows it to a person (the guide's door, the start text to copy), it shows the same English text.

## What does it leave alone?
- What a person reads: a screen's labels and help lines, a guide a person follows in the panel (the Application Access guides), an answer's translated `message` beside its English `hint` (`decisions-made-product-85.md`). These stay translated (`core/translation-rules.md`).
- What a person writes for an agent (an agent's own instructions, a channel's memory): the software does not translate it either way.

Related: docs/memory/workflow/decisions-made-product-85.md, docs/memory/api/api-change-rules.md, docs/memory/workspace/workspace-writing-rules.md
