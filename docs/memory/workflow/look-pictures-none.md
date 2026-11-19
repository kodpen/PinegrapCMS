---
name: look-pictures-none
description: No step sends a picture of its screen for approval since 2026-11-19: a worker labels without one, the owner looks on dev after it lands; questions go to the architect in the channel
---

## Does a step send a picture of its screen for approval?
- No. Owner (2026-11-19, agent channel message 9531): "ekran görüntüsü onaylarıyla fazladan token yakmaya gerek yok mimara sorunuz olursa kanaldan sorulabilir."
- A worker marks its step ready without a picture to the architect, and no step waits on one. The owner looks at the screen on dev after it lands and says what to change. A plan line that still asks for a picture is not followed.
- A question stays a question: one message in the agent channel naming the architect (`architect-asking.md`).

## What is left of the pictures?
- The architect's reading, not his words: the approval path of `architect-asking.md` is used only when the owner himself asks to see a screen before it lands. A poll may still carry a picture when the question cannot be put in words (`owner-question-picture.md`).

## Why?
- In the week measured the architect's session read 409 pictures, each call on a context of 200,000 tokens and more (`quota-use-measured.md`).

Related: docs/memory/workflow/architect-wake.md, docs/memory/workflow/coordinator-worker-brief.md
