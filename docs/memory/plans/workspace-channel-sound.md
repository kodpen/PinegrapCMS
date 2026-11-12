---
name: workspace-channel-sound
description: Plan (owner 2026-11-11, #8720): a short sound in Workspace when something for the person arrives while that conversation is not in front of them; one step, CS1
---

## What is it for?
- The owner in the agents channel (2026-11-11): "kanaldan yanıt geldiğinde sesli mesaj sesi mi çıkartsa kanal teams teki gibi vs.". Asked with three options, he chose the sound only for what is for the person (#8720, `workflow/decisions-made-workspace-14.md`).
- Today a Workspace channel makes no sound. The panel's chat window has a short tone made in the browser (`playTone()`, `assets/js/chat_backend.src.js`); assistants' answers read aloud is another thing (`workspace/workspace-voice-read-aloud.md`).

## Which steps are there?
- CS1 (S, under 400 product lines) The sound. `in progress`.
  - It sounds for: a mention of the person, a direct message, an answer to their own message or request (an agent's answer too), a question asked of them. Never for their own message, never for other channel traffic.
  - Only while that conversation is not in front of them: another channel open, the tab in the background, or the window not focused.
  - One short tone made in the browser (no sound file), the chat window's way; several arrivals at once sound once. A page that has not been clicked yet makes no sound (the browser's rule) and shows no error.
  - A per-person switch in the channel menu, on by default, kept in the browser like the read-aloud switch (localStorage, the viewer's choice, not the channel's). No schema.
  - What is "for the person" is read from what the page already gets for its unread marks and the bell (`ui/notifications-one-inbox.md`); no new request to the site only for the sound.
  - Tests: which arrivals sound (a pure function over the arrival and the viewer), the once-per-batch rule. Texts through `lang()`, keys by `php tools/tr_add.php`; `php tools/build_js.php` after the edit.
  - Topic: `workspace/workspace-channel-sound.md`.

## Who holds it, where does it start?
- Coordinators hand the step out; claim as `[plan:workspace-channel-sound] CS1 — The sound`. It edits `workspace.js`, which the open steps of `workspace-channel-look` edit too: the claim check holds. The step's PR deletes this file.
