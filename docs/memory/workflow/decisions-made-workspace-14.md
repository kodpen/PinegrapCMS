---
name: decisions-made-workspace-14
description: Decisions list, workspace part 14 (2026-11-11 on): the channel sound only for what is for the person, an agent waits while the message that asked it is being edited
---

## Where are the rules and the earlier parts?
- `decisions-made.md` holds the rules; `DECISIONS.md` lists the entries of every part. Entries name the issue and the date.

## When does a Workspace channel make a sound for a person? (owner, 2026-11-11, #8720)
- Only for what is for the person: a mention, a direct message, an answer to their own message or request (an agent's answer too), a question asked of them. Only while that conversation is not in front of them. One short tone; a per-person switch in the channel menu, on by default.
- Owner (2026-11-11): "Yalnızca bana gelenlerde (Önerilen)", asked in the architect's window after his words in the agents channel: "kanaldan yanıt geldiğinde sesli mesaj sesi mi çıkartsa kanal teams teki gibi vs.".
- Not chosen: a sound on every new message; no sound.
- The architect's, not the owner's words: the tone is made in the browser with no sound file, several arrivals at once sound once, the switch is kept in the browser. Plan: `plans/workspace-channel-sound.md`.

## What does an agent do while the message that asked it is being edited? (owner, 2026-11-11, #8723)
- It waits while the writer has the message's edit box open; the line under the message says so. Saving hands the request on with the new words; cancelling, or two minutes without a keystroke, with the words of before. An agent that had already started stops and reads the new words. An answer already given is not asked again.
- Owner (2026-11-11): "Düzenlerken beklesin, yeni metni okusun (Önerilen)", asked in the architect's window after he raised it there: "bir ajanı andım ama mesajı düzenlemeye başladım bu durumda mesajın düzenlendiğini ajan görse ve yanıt için acele etmese nasıl olur? bu hem ajan kanalı hem de normal kanallardaki ajanlar için".
- It holds in every channel kind and for every agent kind.
- Not chosen: only a saved edit counts, with no hold; a short wait on every mention.
- Why: before it, the request was made at once on send and an edit reached neither the queue nor the brief, so an agent could answer the words of before. Plan step: A10 of `plans/agent-channel-steps-3.md`.
