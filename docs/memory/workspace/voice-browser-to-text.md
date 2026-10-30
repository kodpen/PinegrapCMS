---
name: voice-browser-to-text
description: "Convert to text" under a voice message by the browser's own model (pg_voice_text.js, owner request 2026-10-29): where it shows, Prompt API with audio input, pieces of 30 s, nothing sent or kept
module: voice_text
---

## What is it? (owner request, 2026-10-29)
- Under a voice message the person can already play, a "Convert to text" button, only where the browser's built-in model hears audio. The browser writes the recording out; the text appears under the player for that person, marked "Written out by this browser's built-in AI, for you only". It is never sent to the server nor stored: leaving or redrawing the screen drops it. No schema, no new access (the file is fetched with the same address and cookies the player uses).
- Three places: Workspace channel messages and discussions, the recordings of a Workspace note (both through `PGWsVoice.player()`, workspace_voice.js), and the panel's chat window (`attachmentNode()` in chat_backend.src.js). Not the site's signed-in chat widget.
- A message with a server transcript (V3, `file.voice` done) or one on its way (queued/running) shows that instead and no button (workspace-voice-screen.md); failed, skipped or never asked offers it.

## Which browser path, and why?
- The Prompt API (`LanguageModel`) with `expectedInputs` `[{text, ['en']}, {audio}]`, the family the designer's alt-text button uses with image input (ui/ai-write-alt-text.md). It runs on the device, so the recording never leaves the browser, and it reads a file that already exists.
- Not the Web Speech API (`SpeechRecognition`): it hears a live microphone or track in real time, and Chrome's default sends the sound to Google's servers; on-device recognition there is newer and narrower. Not a server service: the owner asked for the browser's own.
- `ready(locale)` asks `availability()` once per screen for output in the screen's language, then English; anything but `unavailable` shows the button (a model still to download is created on the click, which carries the gesture). The language list is the model's (en, ja, es, de, fr in Oct 2026); a Turkish screen falls to English output, the prompt asks for the words in the spoken language.

## How is a recording written out?
- On the click: `LanguageModel.create()` at once (download progress in a `<progress>`), the file fetched and decoded by the browser's own `AudioContext` (WebM, Ogg, MP4), the first channel cut into 30-second `AudioBuffer`s (`PIECE`). Each piece is asked on a `clone()` of the session, so earlier pieces do not fill the context; the text grows piece by piece. Any error, or nothing heard, says "The recording could not be converted to text." and shows the button again. The session is destroyed afterwards.

## Where are the files and texts?
- `assets/js/pg_voice_text.js` (`window.PGVoiceText`: `ready`, `control`, `language`), served alone as `pg_voice_text.min.js` (a source sits in one bundle only): `ws_screen_assets()` (includes/workspace/screen.php) loads it before `workspace.min.js` (a record screen with the page, before its loader), `pg_chat_render_backend_launcher()` before chat_backend; a second load on the same page returns at once.
- Texts `pg_voice_text_strings()` (includes/fn/voice_text.php), added to `ws_voice_js_strings()` and to the chat launcher's strings only.

## What is unverified?
- No browser run: the sandbox has no Prompt API. Audio input was announced for Chrome's Prompt API (desktop, with Gemini Nano; Chrome 138+ extensions, the web later); whether a given Chrome exposes it on the web, how long a piece it takes and how well it hears Turkish are not checked. Edge, Firefox and Safari show no button as far as known.
- Keyboard focus and the meter name: `ui/voice-text-keyboard.md`.
- `tests/voice_text_test.php`: the control hidden without the model, the language order, three pieces for 70 s joined with the mark, the file only read, a failure offering it again (Node with stubs), and the wiring.
