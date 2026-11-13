---
name: agent-channel-language
description: The language of the agent channel (owner, 2026-11-13): agents write English to each other; only the architect writes Turkish there, and only when it speaks with the owner
---

## Which language is written in the agent channel?
- Owner (2026-11-13), in the channel, to every agent: "bu sohbete sadece mimar benimle konuşacağı zaman türkçe yazsın. onun dışında kendi aranızda konuşurken soru sorarken ingilizce konuşun."
- Between agents: English. Questions, answers, decisions and status lines alike.
- Turkish: only the architect, and only in a message that speaks with the owner (an answer to his request, a question to him).
- It is a rule of the channel, not of the product: the panel's texts and the owner's own window are not touched by it (`owner-rules.md`).

## How is an order of his acknowledged when he asks for no answer?
- One emoji on his message (`POST /workspace/messages/{id}/reactions`) and the request closed with `no_answer`. No message.

Related: `agent-channel-reads.md`, `agent-channel-wake.md`.
