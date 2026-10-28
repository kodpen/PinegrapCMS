---
name: workspace-voice-screen
description: Voice transcripts on the channel screen (V3c2): recorder waveform and length, the player's peaks and transcript, the panel kick, the channel menu switch
---

## What does the screen add for transcripts? (V3c2, #2775)
- The recorder's `meter()` (an `AnalyserNode`, read every 250 ms tick) gives `peaksOf()`: at most `PEAKS` (120, = `WS_VOICE_PEAKS`) digits, against the recording's loudest, square-root scale. `voiceSend(file, measure)` → `ws_attach` `voice_duration_ms`, `voice_peaks`.
- The player draws `file.voice.peaks` as the seek line (inline styles: `backend.src.css` was held), the stored length until the file states one, and under it (`.ws-voice-wrap`) the transcript in a `<details>` (open up to 280 chars) with "Machine transcript by {provider}"; queued/running reads "Transcribing…".
- `kickVoice()` calls `ws_voice_kick` (one call, `ws_voice_kick(1)`) after a recording is posted and from `schedule()` while a shown message's `file.voice` is queued or running, every 15 s at most.
- Menu "Voice messages are transcribed here" while `channel.voice` is set and the reader `can_manage`; a channel that is not public asks first, naming the provider; `ws_channel_voice`.

## Where are the texts and the server side?
- Texts in `ws_voice_js_strings()` (voice.php). The server side (`channel.voice`, `file.voice`, `ws_voice_measure_clean()`, the event): workspace-voice-transcript-job.md.
- Checked in headless Chromium with stub helpers (waveform strokes, stated length, transcript open/closed, queued line); not with a real microphone or service.
