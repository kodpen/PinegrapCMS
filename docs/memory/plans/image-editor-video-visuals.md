---
name: image-editor-video-visuals
description: Screens of plans/image-editor-video.md (V0), 35 to 42 in plans/image-editor/: what each shows and which step builds it
---

## How were they made and how are they read?
- Over the real editor of development, with the parts not built yet drawn into it from its own classes and tokens; Turkish labels, 1280 × 800 and 390 × 844 like screens 01-34 (`image-editor-visuals.md` and the pictures in `image-editor/`).
- They fix behaviour and layout; icons, words and spacing may be bettered in the step, said in its PR.
- Canvas screens, light. The video frame is drawn as a picture; the clip rows, waveforms and panels are drawn over the real tracks (`ui/image-editor-tracks.md`). The scene strip (AN7) is left out to give the rows room.

## Which screen goes with which step?
- 35-video (V2, V3, V4): a 1920 × 1080 canvas with a video as the bottom layer and texts over it, Animate tool, the clip selected. Its row is taller: two clips (a filmstrip, the clip's name and source times, its sound as a thin waveform under the frames); a music row with its waveform. The Video panel: the file (thumbnail, size, length), tabs Klip, Efektler, Hareket (Hareket is AN1's In, Loop, Out), source in and out, speed chips 0.25-4×, volume with a mute button, Split (at the playhead), Detach sound, Duplicate, Delete; Değiştir swaps the source. Added from Files or this computer through İçe Aktar (E31).
- 36-video-trim (V3): the clip split at the playhead (the dashed cut with scissors) and the second part's end being trimmed: the end snaps (amber line) to another layer's end, the clips after it on the row follow with no gap, the length becomes 11.2 s. While an end is dragged the stage shows that end's frame and a pill names its source time. The panel shows the part's source and timeline times; its name says it shares the source.
- 37-sound (V4): an animation with no video (1080 × 1350): a music row with its waveform, volume and the fade in and out drawn as an envelope, a short sound (Zil.wav) at 2.2 s. The Sound panel: the file, source in and out, start, volume and mute, fade in and fade out, Split, Duplicate, Delete; the note on the formats and that MP4 and WebM carry the sound.
- 38-video-effects (V5): the Efektler tab: brightness, contrast, saturation, warmth, blur, vignette per clip (the stage shows them), the transition to the next clip (none, fade, slide, wipe, zoom) and its time. The clip's row: a 2× badge, its waveform faded (sound muted off 1×), an effects mark, the transition's button between the two clips.
- 39-video-pip (V6): a second video layer small over the first: rounded corners and a white border from the panel (Köşeler, Kenarlık), handles on the stage; its own row from 2 s to 9 s. Texts with an end: entrance, a line while they stay, the exit, nothing after.
- 40-video-save (V7): Save animation on MP4 video: under the formats a line that GIF, APNG and WebP carry no sound; a Sound field (AAC 128 kbps stereo; Opus for WebM) with "Add sound" naming the sources; the estimate counts the sound; the foot says frames and sound are drawn, mixed and encoded in the browser.
- 41-video-progress (V7): the same dialog working: video frames decoded, sound mixed, frames drawn and encoded with the count, sound encoded, saving; Cancel writes nothing.
- 42-phone-video (V10), 390 × 844: the sheet holds the play bar, then one list of rows (the name above its lane, one time ruler, the playhead across); the clip row tall with finger-size trim handles; the sound rows folded into one line ("Ses · 2 satır") that opens them. Split joins Duplicate and Delete in the selection bar.

## What is not on a screen?
- V1 (its message for a source the browser cannot decode), V8 (chunked upload and save: AN4's progress with the parts), V9 (reopening), V11 (keys J, K, L, I, O and captions).
