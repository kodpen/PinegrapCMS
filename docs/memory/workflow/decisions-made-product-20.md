---
name: decisions-made-product-20
description: Decisions list, product part 20 (2026-11-02 on): voice to assistants (who writes a recording out, when an answer is spoken) and the owner answers that follow
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue and the date.

## Voice to assistants: the sender's browser writes the recording out, a voice ask is answered by voice (2026-11-02, #5390)
- Owner: without a site key the sender's browser writes the recording out on the device and stores it as a machine transcript, Turkish tried first; where the browser's recognition proves unusable the site's key stays the way, and a Pinegrap AI gateway preset follows once the gateway offers audio. An ask that was a recording is answered by voice: by the speech service when one is set, else read aloud by the asker's browser even with the read-aloud switch off; a text ask gets text. Reason: Claude's API takes no sound, so the recording is written out before the request, and the person who spoke expects to hear the answer. Plan: `plans/voice-to-assistants.md`.
