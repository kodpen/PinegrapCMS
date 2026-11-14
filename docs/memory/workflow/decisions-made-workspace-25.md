---
name: decisions-made-workspace-25
description: Decisions made, Workspace part 25: when an agent may conclude a discussion (asked by a person in it; unasked it only offers a button a person presses)
---

## May an agent conclude a discussion? (owner, 2026-11-14, in the architect's window)
- What happened: in a discussion of the agent channel he told the architect "tartışmayı kapatabilirsin". An application's key cannot conclude a discussion (only a person in it can, in the panel), so he concluded it himself.
- Asked which rule an agent should have, he answered: "istenince sonlandırsın. istemeden de tartışmanın sonuçlandığını düşünüyorsa sonlandırma düğmesi sunabilir. kullanıcı tıklayınca tartışmayı sonlandırır."
- So: an agent concludes a discussion when a person in it asks it to. Unasked it never concludes; when it thinks the discussion has reached its end it may offer a Conclude button, and the discussion is concluded when a person presses it.
- The architect's reading, not his words: "a person in it" leaves out a guest, a mail and another agent (`workflow/agent-channel-guests.md`); the rule holds in every channel's discussions, not only an agent channel's.
- Plan: `plans/agent-conclude-discussion.md` (AD1 on request, AD2 the offer).
