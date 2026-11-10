---
name: image-editor-sound
description: Music and sounds on an animation (V4): doc.sounds on the whole timeline, sounds from Files, the rows under the tracks, the Sound panel, Web Audio in step with the play bar, the sound_files action
module: image_editor
---

## What does a document hold?
- `doc.sounds`: `{ id, fileId, name, src, length, start, from, to, volume, fadeIn, fadeOut, muted }`, times in ms. `length` is the file's, `from`/`to` cut it, `start` places the cut on the whole animation's timeline (every scene back to back, `motionTotal()`), not in a scene: music runs across scenes. No sound drops the key.
- Not layers on purpose: a sound has no box, so selection, hit tests, Layers, the frames and a still save never meet it. A clip's sound detached (V2) will be an entry here.
- The model's `SOUND` section reads it through `M.soundList(doc, origin)`: an entry with no id, or an address that is not the site's own (`M.soundSrcOk()`: a root path, or http(s) of the same origin; `//host` refused), is left out, the numbers are put in range, at most 20 (`SOUND_MAX`). So an opened document never makes the editor fetch another host. Every change goes through `soundChange()`, which writes that clean list back: one undo step each, a slider's moves one step by its key.

## Where do sounds come from?
- Files only (`sound_files`: MP3, M4A, AAC, WAV, Ogg, Oga, Opus by `files.type`, no Recycle Bin, no ERP document, a role-3 user's folders, newest 60, a search). From this computer is left to V8's upload. Add sound sits at the end of the rows and in the panel's Sounds list (the only way on a phone).
- The file is decoded first; `M.soundNew()` puts it at the playhead, cut to what is left of the timeline (whole when nothing is left), picked.

## How does it play?
- One `AudioContext` per editor; each file is fetched and decoded once (`soundBufs` by address); a decoded file draws the rows again.
- The docks' `update()` runs with every playhead move: `soundSync()` plays from the playhead while the play bar plays (not a phase's preview), and again when the sound's clock and the playhead differ by over 150 ms (a loop, a scrub). Each sound is a buffer source from `M.soundPlan()` with its gain ramped between the fades' corners (`M.soundGain()`). Pause, seek, leaving the tool and closing the editor stop it.
- An MP4 or WebM export carries it, mixed and encoded in the browser (`ui/image-editor-mix.md`); GIF, APNG, WebP and PDF do not.

## What do the rows and the panel do?
- A `.pgie-tracks` of its own under the tracks (`Editor.motionDocks`, bundled after the tracks), not on a phone (the same media query). Its own ruler when the tracks count one scene or there are none. A bar holds the cut part's waveform (`M.soundPeaks()` of channel 0), the volume's line with the fades, the name. Drag the middle: start; the ends: trim (`M.soundEdit()`, 0.1 s steps, Shift 0.01 s, 0.1 s stays); one change on release.
- A picked sound (`soundSel`) gives the Animate panel the title Sound and: start, from and to in the file, volume and mute, fade in and fade out, Split at the playhead (`M.soundSplit()`), Duplicate (after it), Delete; Remove in the header and Delete or Backspace delete it; picking a layer, a press on the stage or Escape leave it. Sliders instead of the time fields of screen 37.

## Where is it tested?
- `tests/image_editor_sound_test.php`: the clean list and addresses, a new sound, the gain and the plan, a drag, a split, the peaks and types, the files listing.
