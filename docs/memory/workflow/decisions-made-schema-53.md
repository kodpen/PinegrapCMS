---
name: decisions-made-schema-53
description: Decisions made, schema part 53 (2026-11-26 on): an agent's context size typed on its form, kept in ws_agents.context_size; imports.kind takes squarespace, webflow, wix
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-schema-52.md`.

## Where does an agent's context window size come from? (owner, 2026-11-26, agent channel polls 227 and 228, messages 14104 and 14109)
- What he saw: on the agent card the Context row was empty for most agents, and k3's said "116k of 1M" while its window is 250k.
- Why: the report takes the context only as used and size together, and a session cannot read its window's size. It reads its used tokens from its own transcript (the last message's usage: input, cache creation and cache read tokens together); most sessions sent nothing, k3 sent a used value with a guessed size.
- Poll 227, asked with two choices (he types the size on the agent's form; or no size is shown at all). Owner: "Toplamı ajan formunda ben girerim, halka ona göre dolar".
- Poll 228, where it is kept (one column; or one `config_text` row of JSON, no schema change). Owner: "ws_agents tablosuna tek sütun: context_size".
- So: `ws_agents.context_size` INT UNSIGNED NOT NULL DEFAULT 0, 0 for not entered, added by a step of the open version with `install_add_column()`. An application agent's form gets the field; the ring and the percent are drawn from it.
- An agent reports the tokens it uses and never guesses the size. With no size entered the row says the used tokens alone and draws no ring.
- Architect (2026-11-26, agent channel request 3118), the reading the step builds: the report and the answer route take a context with `used` alone; a `size` an agent still sends is kept and used only while the form's field is empty, so a harness that does read its window keeps working. Used above the size shows as full, never as a refusal of the report.
- Running sites: the column's default changes nothing; a context stored before reads as it did.

## Which kinds does an import record take? (owner, 2026-11-27, agent channel poll 253, message 14972)
- Asked when the Squarespace reader was written: `imports.kind` is an `ENUM` of wordpress, woocommerce and shopify, so an import from another service could not be recorded under its own name. No table and no column is added and no stored row changes; only the values the column takes grow.
- Owner: "Üçü birden eklensin". Offered beside it and not chosen: only squarespace now; no value added.
- So: one step of the open version widens the `ENUM` with `squarespace`, `webflow` and `wix`, the way `decisions-made-schema-48.md` describes (the MODIFY runs only while a value is missing, a new install's CREATE TABLE carries the same list); never a `VARCHAR`. It is written with IB2 of `plans/import-site-builders.md`; the Webflow and Wix readers find their value there and add none.
- Architect's reading, not his words: a collection's CSV has no kind of its own; it is recorded as webflow or wix, whichever its file is told to be.
- Running sites: an import made before keeps its kind; nothing is rewritten.
