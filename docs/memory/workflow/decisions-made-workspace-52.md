---
name: decisions-made-workspace-52
description: Decisions made, workspace part 52 (2026-11-26 on): an application agent's answer to another agent's request is refused when it would not wake the asker, with words that say what to write
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-workspace-51.md`.

## Is an agent's answer that does not name the asking agent refused? (owner, 2026-11-26, agent channel poll 226, message 14090)
- Why it was asked: an agent that answered another agent without its name reached nobody. It happened on 2026-11-20, on 2026-11-25 and again on 2026-11-26 (the merging session's messages 14029, 14037, 14060), although the instructions, each brief's "Who wakes here" block, the channel memory and `agent-channel-answer-handle.md` all say it. The owner (message 14085): "pr merge aynı hatayı defalarca yapıyor stabil gibi gelmiyor bana".
- Asked: should the site refuse such an answer, the refusal saying what to write.
- Owner, in his own words under "Other": "duruma dayalı bir red mantıklı ama direkt red mantıksız olur. Ajan kanalında yanıtlarla uyanma da açılıp kapanabiliyor sonuçta. sadece anılınca uyanma açıksa evet adsız yanıt reddedilsin ki ajan ne yapması gerektiğini anlamış olsun, koordinasyon sürsün".
- So: the refusal depends on the asking agent's wake rule. An answer is refused only when it would not wake the agent that asked; where that agent wakes on answers to its messages, an answer without its name is taken as before.
- Architect (2026-11-26, agent channel request 3115), the reading the step builds: on the answer route of a request that an application agent's message made, the answer's words are asked of the same rule that makes requests (`ws_agent_wake_matches()` for the asker, as an answer to its message written by an application); no match is a refusal whose words name the asker's handle and the two ways on: begin with the handle, or close with "no answer needed". A dry run meets the same refusal.
- Not touched: an answer to a person, "no answer needed", an emoji alone, a task run's answer, an asker that is paused or no longer allowed in the place. The wake rule "Replies to its messages" stays off in the agent channel (`agent-channel-language.md`); the refusal does not turn it on.
- No schema change.
