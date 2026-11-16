---
name: channel-commands-census
description: Census for extending the / commands of Workspace channels (owner 2026-11-16, agent channel message 6671), start file: his words, the parts, what binds a new command, what is still to decide
---

## What is asked?
- Owner (2026-11-16, agent channel message 6671): "@arge kanallardaki komutları genişletme ilgili araştırma ve plan hazırla. en son mimara sor planı o hazırlasın. tr saatiyle 17:00 da kotası açılıp devam edecek"
- So the R&D agent made this census and a proposal, and the architect writes the plan. These files are its input: the plan takes over what lasts and they are deleted with its first step.

## Where are the parts?
- The engine, what one new command touches, where a command runs: `channel-commands-census-engine.md`. What is missing or looks wrong: `channel-commands-census-findings.md`.
- What has a button and no command: `channel-commands-census-actions.md`. The site records a command could write: `channel-commands-census-records.md`.
- Other team chats: `channel-commands-census-others.md`. The R&D agent's proposal: `channel-commands-census-proposal.md`.

## Which decisions bind a new command?
- A command is a shortcut, never the only way: each has a button somewhere on the screen (the header of `includes/workspace/commands.php`).
- Owner (2026-10-29, `workflow/decisions-made-workspace-2.md`): a create inside a chat goes through /create (/oluştur), never a window opened in the chat; the channel's menu holds only the channel's own items.
- Architect (2026-10-29, #2999, the same file): no new kinds for /create "for now (task, customer, sales quote, workflow table row are asked when a need appears)"; /task stays its own command.
- Architect (2026-10-30, #3801, `workflow/decisions-made-product-11.md`): commands run over the API and get no MCP tool; refused: record-card commands (/sipariş…) and Enter saving a /price or /stock card.
- Owner (2026-11-05, #7110, `workflow/decisions-made-product-33.md`): a channel message starts a flow only when it mentions the flow; "A keyword start is not added until asked".
- Scheduled work is set up only on the flow canvas (`workspace/workspace-scheduling-one-place.md`).
- An application's command never takes a built-in name, is never posted and goes to its application alone (`workspace/workspace-app-commands.md`).

## What does the census say in short?
- Twenty-two built-in commands, strong where other chats are weak: typed arguments with a popup, a live line, a card before a write, Turkish names, an unknown /word never posted. The work commands (tasks, plan, decisions, a product's price) are the product's own.
- Missing against every other chat: the channel's and the person's everyday commands (a reminder, invite, leave, mute, topic, search), an answer of its own and typed arguments for an application's command, a command a team makes without code.
- One new command touches eight places in the code, up to eleven with a new kind of argument or answer; nothing counts the use of commands.

## What is still to decide?
- The owner's, asked with the architect's view: which packages of the proposal are planned; whether a by-hand flow may be started by a typed command; whether an agent hears a command typed for it; the column for an application's arguments.
- The architect's: the shape of the registry, the names of the new commands, which channel items stay menu-only, whether counting is worth a place to keep it.
