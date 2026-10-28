---
name: workspace-voice-asks
description: A voice message that asks Claude, Pinegrap AI or an MCP server (V4c): tags beside the mic, a voice answer, asked once transcribed (ws_voice_posted(), ws_voice_ask())
---

## When is an assistant asked by a recording? (V4c)
- Its words ask as a message's do (`ws_message_ask_assistants()`): a tag (`<@app:N>`, `@ai`, an alias) typed beside it, or an answer to one of an assistant's answers. Before V4c an upload asked nobody.
- `ws_voice_posted()` after both uploads (`ws_message_send_file_bytes()` for the panel, files.create when `ws_api_message_asks_assistants()` lets it): a recording `ws_voice_queue()` queued asks nothing yet; one that will not be written out (transcripts off, the channel closed to them) asks at once. Any other file still asks nobody.
- `ws_voice_ask($message_id)` once the row leaves the queue for good: `ws_voice_run()` done, `ws_voice_finish()` (skipped, failed), the watchdog's failures. It asks in the name of the person who posted it (`ws_ai_viewer()`), only for a `user` message not deleted, while they may still post there. Claude's routine is called at once (`ws_claude_dispatch()`); Pinegrap AI and an MCP agent are carried on by the screens showing the message, as after any request.
- The watchdog now runs before the transcripts switch in `ws_voice_kick()`, so a recording queued before transcripts were switched off still fails after a day and asks.

## What does the assistant read?
- `ws_ai_heard_text()` through `ws_voice_heard()`: the words, then `[voice message, machine transcript: …]`, or `[voice message, not transcribed]` for a recording without one, so the answer says it did not hear it rather than guessing. Claude reads `message.transcript` (workspace-voice-assistants.md).

## What does the screen do?
- The mic stays in place of Send while the box holds only tags (`voiceTags()`: tokens and `@ai`, nothing else); Stop sends them as the recording's body (`voiceSend()`), and they leave the box (`clearSent()`). Words in the box still stay there and do not go with it.
- `voiceAnswers()` is false only for an MCP agent's message (it reads words alone): the mic answers Claude and Pinegrap AI too. `ws_attach` answers `ai` and `agent` and starts Claude's run when a recording asked at once.
- Not checked in a browser with a real microphone or service; the tag test was run against the regular expressions in Node.
