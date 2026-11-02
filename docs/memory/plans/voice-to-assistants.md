---
name: voice-to-assistants
description: Plan (owner 2026-11-02): recordings reach every assistant in channels and discussions, assistants answer by voice, any caller can post a voice message; steps VA1-VA6
---

## What is wrong now?
- An assistant hears a recording only through its transcript, and transcripts need the site's own OpenAI-compatible key (`workspace/workspace-voice-transcripts.md`). Without the key the ask goes at once as `[voice message, not transcribed]`, and Claude answers that it could not hear (`workspace/workspace-voice-asks.md`). Nothing tells the person why or where to set it up. On dev.pinegrap.com, 2026-11-02, `ws_voice_enabled` was 0 and seven recordings were never written out.
- Answers are text only. The browser reads them aloud only behind a per-person switch, off by default (`workspace/workspace-voice-read-aloud.md`). No sound file is made, so another device hears nothing.
- No route posts a voice message. When asked to "send a voice message", neither Claude's routine, Pinegrap AI, nor an application on the site's MCP server (the Claude or ChatGPT connector) can do it.
- The microphone does not answer an MCP agent's message (`voiceAnswers()` in workspace.js), although `mcp_ask.php` already reads transcripts.

## What is decided?
- Owner (2026-11-02):
  - recordings reach Claude, Pinegrap AI and MCP agents in channels and discussions;
  - assistants answer by voice where possible;
  - "send a voice message" works for every caller, including connector clients.
- Earlier rules stay:
  - the body is the person's words; a transcript is machine text (#2417);
  - `ws_voice_channel_allowed()`, the 5-minute cap and delete-for-everyone apply unchanged;
  - settings live in the panel, never in `data/config.php` (`workflow/decisions-made-product-19.md`).
- Claude's routine reads text and images and cannot hear a sound file. The recording is therefore written out before the request, either by the site's service or on the sender's device.
- A spoken answer is the assistant's own voice message: an audio file with a `done` `ws_voice` row whose transcript is the answer's text. Search, recaps, bells and the player therefore work unchanged. Speech settings are `config_text` rows like the transcript key, so no schema change is needed.

## Which questions are open? (#5390)
1. Who writes a recording out when the site has no key? (a) only the site's key, with VA1's hint; (b) a Pinegrap AI gateway preset on the subscription key, which needs audio endpoints on api.kodpen.com; (c) the sender's browser on the device, with the text stored as a machine transcript. Recommended: (c) now, (b) once the gateway offers audio.
2. When is an answer spoken? (a) whenever the ask was a recording: by the speech service when one is set, else by the asker's browser even with the switch off; (b) only with the switch on. Recommended: (a).

## Where does it start?
- Read the `workspace/README.md` key rules, the `workspace/workspace-voice-*.md` files, `workspace/voice-browser-to-text.md` and `workspace/workspace-claude-bootstrap.md`. Steps: `voice-to-assistants-steps.md`.
