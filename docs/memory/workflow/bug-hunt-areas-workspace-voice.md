---
name: bug-hunt-areas-workspace-voice
description: The bug hunt of Workspace voice messages (V3-V5, VA1-VA6), 2026-11-26, #11809: five faults fixed in the transcript job, the voice ask and the speech answer; what read clean and what was left
---

## What was the area?
- Given by the architect on 2026-11-26: Workspace voice messages, `includes/workspace/voice.php`, the voice route in `api_resources.php`, `ws_attach` in `actions.php`, `speakAnswer()` in `workspace.js`. Start file `workspace/workspace-voice-overview.md`. Calls, a recording in a note, "Convert to text" and the AI connections card were outside it.

## What did it fix? (#11809, tests/workspace_voice_hunt_test.php)
- A recording deleted for everyone while the service wrote it out still had its transcript sent to the webhooks (`workspace.message.transcribed`). The done UPDATE now needs `status = 'running'`, and a message read again as deleted is not announced.
- A recording posted through an OAuth connection asked the assistants once written out, though its route asks nobody. `ws_voice_ask()` skips `via_client_id` messages.
- Any bytes named `voice-….mp3` (an API upload) were sent to the service. `ws_voice_sound_rule()` checks the file's first bytes first.
- An empty text from the service was kept as a done transcript, so the assistant got "not transcribed" without a reason. Now failed, "heard no words".
- A 200 error page (`text/html`) from the speech service was taken as a sound and cleared the card's error. Now a refusal.

## What read clean?
- `ws_voice_channel_allowed()` asked on every path out: job, browser route, spoken answers, the route and Pinegrap AI's tool. The event goes out for public channels only.
- A failed transcript still asks once, with the "not heard" line: Architect (2026-11-26) kept it, as `workspace-voice-asks.md` says.
- Retries and their end, the watchdog's single ask, the hold path, the browser route (only at upload, recorder name, 100,000 chars), the 1,500-character cut on Turkish text, search, recaps, bells (deleted messages skipped).

## What was left?
- The 5-minute cap is only the recorder's and 6 MB on the server: an API upload at a low bitrate can be longer. Finding the length means decoding the file.
- What one application key can spend at the speech service is bounded only by the general API rate limit, not by the twenty-a-minute message limit, which binds a person only.
