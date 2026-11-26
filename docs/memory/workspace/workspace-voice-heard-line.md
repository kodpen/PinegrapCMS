---
name: workspace-voice-heard-line
description: The line after a voice request that was written out (ws_voice_heard_line()): the machine transcript is how the assistant hears it, so it answers what was said, not that it only reads text
---

## What symptom did it fix?
- On dev (2026-11-26) a voice message to `@ai` whose browser transcript asked "can you hear me?" got the answer that Pinegrap AI is text-only and cannot process voice.
- The transcript was in the request block (`[voice message, machine transcript: …]`), but nothing told the model that this is how it hears voice messages, so it answered the literal question with no.

## What does the assistant read now?
- After the request block, when the request is a recording written out, `ws_ai_briefing()` and `ws_mcp_ask_start()` add `ws_voice_request_heard_line($message)` outside the data blocks, as the site's own line.
- The line says: the request is a voice message, written out as a machine transcript, which is how it hears voice messages; answer what the transcript says as the request; a word may be misheard; never answer that it cannot hear, receive or process voice messages or audio.
- It is the counterpart of `ws_voice_unheard_line()` (`workspace-voice-unheard.md`): one or the other, never both. A message that is no recording, or a recording without a transcript, gets no heard line.
- Pinegrap AI, the model agents (same briefing), Claude's brief (its text is the briefing) and the MCP ask read it.
- The transcript stays in the request block, marked as machine text (owner, #2417). The line holds no transcript text, so the spoken words never reach an instruction line.
- Pure `ws_voice_heard_line($name, $transcript)`: '' unless `ws_voice_is_recording($name)` and `ws_voice_transcript_clean($transcript)` is not empty. `ws_voice_request_heard_line($row)` reads the transcript with `ws_voice_transcript_of()`.

## What is not covered?
- The application agents' brief (agent_queue.php) reads the body with `ws_plain_text()`, without the transcript.
- Asked what it can do, the model may still say it is text-only: the system prompt does not mention voice messages.

## What was checked?
- tests/workspace_voice_unheard_test.php: the line, its cases and the wiring in ai.php and mcp_ask.php. Not run against the live model.
