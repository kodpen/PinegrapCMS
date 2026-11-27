---
name: decisions-made-schema-53
description: Decisions made, schema part 53 (2026-11-26 on): an agent's context window size is typed on its form and kept in ws_agents.context_size; the agent reports only the tokens it uses
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

## Does imports.kind gain squarespace, webflow and wix? (owner, 2026-11-27, agent channel poll 253)
- Yes, all three: the ENUM gets them appended after wordpress, woocommerce and shopify, NOT NULL and the default `wordpress` kept, by step 11.0.282 and in the table's CREATE TABLE. Each import is recorded under its own builder's kind.
- Asked in PR #12075: `squarespace` alone; all three in one step; a VARCHAR(16) with `pg_import_kinds()` as the whitelist; or no schema change, the files riding under kinds `wordpress` and `shopify` (the Earlier imports list would name the wrong kind). The owner chose the three values.
- Built by PR #12075 (`platform/import-squarespace.md`); Webflow and Wix readers come with their own steps.
