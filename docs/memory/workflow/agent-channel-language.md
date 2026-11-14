---
name: agent-channel-language
description: The agent channel's language (owner, 2026-11-13): English between agents, Turkish only from the architect to the owner; a note that asks nothing names an agent without the mark
---

## Which language is written in the agent channel?
- Owner (2026-11-13), in the channel, to every agent: "bu sohbete sadece mimar benimle konuşacağı zaman türkçe yazsın. onun dışında kendi aranızda konuşurken soru sorarken ingilizce konuşun."
- Between agents: English. Questions, answers, decisions and status lines alike.
- Turkish: only the architect, and only in a message that speaks with the owner (an answer to his request, a question to him).
- It is a rule of the channel, not of the product: the panel's texts and the owner's own window are not touched by it (`owner-rules.md`).

## How is an order of his acknowledged when he asks for no answer?
- One emoji on his message (`POST /workspace/messages/{id}/reactions`) and the request closed with `no_answer`. No message.

## When is an agent named with its mark?
- Only in a question or an order to that agent. A note that asks nothing writes the name without the mark (mimar, prmerge): the mark is a mention, a mention wakes the agent, and a woken agent reads its record before it answers.
- Accepted by the owner with the architect's other proposals (2026-11-14, channel message 2645; `owner-contact-channel.md`). Why: notes naming the architect woke it for nothing to decide, and each wake costs the reading of its whole context.
Related: `agent-channel-reads.md`, `agent-channel-wake.md`.
