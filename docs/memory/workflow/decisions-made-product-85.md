---
name: decisions-made-product-85
description: Decisions list, product part 85 (2026-11-20 on): the site explains itself to AI agents (plan ai-guide): who builds it, the guide's language and doors, the compact tool list, hints on refusals
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries.

## Does the site explain itself to AI agents, and who shapes it? (owner, 2026-11-20, agent channel poll 170, message 10442)
- Yes, and the architect develops, widens and starts it. Owner: "Bu konuyu mimara devret bu özelliği mimar geliştirsin ve genişletsin planıda mimar devreye sokar."
- Why (owner, message 10376): agents use Pinegrap from the browser and over MCP; reading the documentation costs time and tokens, so they act on guesses in the designer and elsewhere, and the cost can be high.
- The research he answered: message 10440. The plan: `plans/ai-guide.md`.

## How is the guide written and read? (architect, 2026-11-20, plan ai-guide)
- One guide, built from the code's own registries, so it cannot fall behind the code; in English, as tool descriptions are: its reader is a model. It is not translated.
- No public address: it is read with the API's or MCP's credentials (no scope of its own: it holds no record of the site) or in a signed-in panel session.
- Nothing is written for an agent that the person cannot see: the panel's door is a visible link and a `rel="help"` link, never hidden text or an instruction in an attribute.
- The designer's rules are one text for the built-in assistant and for an agent over MCP; the assistant's prompt keeps its words.
- The canvas guide (`decisions-made-workspace-24.md`) stays as built.

## How does the tool list get cheaper without breaking a connection? (architect, 2026-11-20, plan ai-guide)
- The compact list is a second address the person chooses; the address given out today and its full list are unchanged. Whether the compact one becomes the default is a later yes.
- On the compact address a tool that moves money (`'moves_money' => true`) keeps its own name and is refused through the shared write tool, so the client's question before it names the action.
- No tool groups per connection and no record for them: the consent presets stay the profiles (Design and Content join them) and tick no record write (owner, 2026-10-24).
- A tool's description is at most 600 characters and a whole tool 4,096 bytes; today's longer ones are shortened area by area, and nothing a right call needs is dropped.

## What does a refusal say to an agent? (architect, 2026-11-20, plan ai-guide)
- Beside the translated `message`, a `hint` in English on what to do next and the guide section to read. The refused calls report reads `api_request_log` as it is; no new record.
- These are the architect's readings of the owner's hand-over; he may turn any of them.
