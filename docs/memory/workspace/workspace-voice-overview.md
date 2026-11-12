---
name: workspace-voice-overview
description: Workspace voice messages as a whole: the microphone, Reply with voice, the player and its transcript, Voice message transcripts card, spoken answers, assistants hearing it; which file holds each
---

## What is it?
- A person records a voice message in a channel or a discussion; the site's speech-to-text service (or the sender's browser) writes it out as machine text beside the person's own words.
- Assistants (Claude, Pinegrap AI, an MCP agent) hear it through that transcript, and a recorded ask is answered by voice where a speech service is set up. Built in steps V3-V5 and VA1-VA6.

## Which decisions bind it?
- The body stays the person's words, the transcript is machine text in its own table; private channels closed until allowed: `workflow/decisions-made-schema.md` (Voice messages V3, #2417), the owner's answers in `workspace-voice-transcripts.md`.
- Voice to assistants (the browser's route, a voice ask answered by voice): `workflow/decisions-made-product-20.md` (#5390).
- Where the Workspace settings, the voice card among them, now live: `workflow/decisions-made-workspace-13.md`.

## Which parts are there? (label on screen, code name)
- The microphone in place of Send and the recorder (`workspace_voice.js`), "Reply with voice" on a sound's player, the player with speed and download: `workspace-voice-recording.md`.
- The recorder's waveform, the player's "Machine transcript by …" and "Transcribing…", the channel menu's "Voice messages are transcribed here": `workspace-voice-screen.md`.
- The settings card "Voice message transcripts" (`#ws-voice`), the `ws_voice` table and which channels may send: `workspace-voice-transcripts.md`; the queue, retries, delete for everyone, `file.voice` and the transcribed event: `workspace-voice-transcript-job.md`.
- The recorder's hint when nobody will hear it, and the assistants' "not transcribed" line: `workspace-voice-unheard.md`. The sender's browser writing it out: `workspace-voice-browser-route.md`.
- Assistants, recaps, bells and search reading transcripts: `workspace-voice-assistants.md`; a recording with a tag asks the assistant: `workspace-voice-asks.md`, an MCP agent: `workspace-voice-mcp-agents.md`.
- The card's "Spoken answers" part and the speech service (`ws_voice_speak()`): `workspace-voice-speech.md`; an assistant's answer to a recording heard back: `workspace-voice-spoken-answers.md`; "Send a voice message" from a text (`POST /workspace/messages/voice`): `workspace-voice-message-route.md`.
- The channel menu's "Read assistant answers to me aloud", the browser's own speech: `workspace-voice-read-aloud.md`.

## Where is each part?
- Server: `includes/workspace/voice.php` (settings card, queue, job, speech, rules); the API route in `api_resources.php`; Pinegrap AI's tool in `ai.php`; the MCP agent in `mcp_agent.php`.
- Screen: `assets/js/workspace_voice.js` (`PGWsVoice`: recorder, player, read aloud) and `workspace.js` (`voiceSend()`, `voiceAnswers()`, `speakAnswer()`); texts in `ws_voice_js_strings()`.
- Convert to text by the browser's model: `voice-browser-to-text.md`; a recording in a note, never transcribed: `workspace-notes-voice.md`; on the channel screen: `workspace-channel-overview.md`.

## How is a part added?
- Keep the person's words and the transcript apart: a reader takes the transcript through `ws_voice_transcripts()` / `ws_voice_with_transcript()`, an assistant through `ws_voice_heard()`, never by writing it into the body.
- Ask `ws_voice_channel_allowed()` before any sound or text leaves the site; a new screen text goes into `ws_voice_js_strings()`.
- A new step gets its own `workspace-voice-<name>.md`, named in the parts above.
