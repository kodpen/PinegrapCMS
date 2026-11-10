---
name: image-editor-clip
description: The video layer (V2): type video, source in and out, start, speed, the frame at the playhead, its sound, Import of videos, the clip panel, video_files and video_upload
module: image_editor
---

## Where is it?
- Part `clip`: `assets/js/pg_image_editor_clip.js` (bundled after `video`), the model's `CLIP` section, labels `pg_image_editor_strings_clip()`, actions `video_files` and `video_upload`, its stylesheet section, `tests/image_editor_clip_test.php`. Added in V2 of `plans/image-editor-video-steps.md` (#8483). Decoding is the `video` part's (`image-editor-video-decode.md`).

## What does a layer hold?
- `{ type: 'video', fileId, name, src, length, width, height, sound, from, to, start, speed, volume, muted, x, y, w, h, rot, opacity }`, times in ms. `from`/`to` cut the source, `start` places the cut on the open document's own timeline (`motionNow()`, not the whole scenes' timeline as a sound), `speed` is one of `M.CLIP_SPEEDS` (0.25-4×), so the clip lasts `(to - from) / speed` (`M.clipSpan()`). `sound` says whether the file has a sound track.
- `M.clipLayer()` puts every number in range; `src` must pass `M.soundSrcOk()` or the clip is never fetched.

## How is it drawn?
- On the stage a `<foreignObject>` with a canvas in the layer's box (presses pass to the stage), so it stacks among the layers: arrange puts it under or over them.
- `M.clipAt(l, t)`: at rest (`this.time` null: outside Animate, every save) the source in; off its span null, and the node is hidden. A motion dock with no rows (`Editor.motionDocks`) calls `clipsUpdate()` with each playhead move.
- `clipDraw()` asks the source's `frameAt()` one request at a time per clip; the newest time waits and goes next, so playing or scrubbing never queues stale frames. The stage's frame is at most 1280 px on its long edge.
- A save paints the source in at the file's own size (`ready()` decodes it first). An animation's export still takes that frame for every frame (open after V7, #8484); its sound is mixed in (`image-editor-mix.md`).

## How does its sound play?
- The sound part plays `this.sounds()` plus `this.soundsMore()`: the clips' own sound as sound entries (`M.clipSound()`, offset by the scene's start). Silent when muted, hidden, without a sound track, or off 1× (the sound would not match). Its file is fetched again for `decodeAudioData()`.
- Detach sound (`M.clipDetach()`, only at 1× with a sound track): an entry of `doc.sounds` on the same file and times, the clip muted.

## How is a video added?
- Import (`image-editor-import.md`): From this computer takes `.mp4`, `.m4v`, `.webm` too (and a drop on the stage): the file is opened in the browser first (a codec it cannot decode is said before any upload), then sent to `video_upload` and lands beside the open file or in the unsaved canvas's folder (edit right, not the bin; type by its bytes, `pg_image_editor_clip_format()`; over one request in parts, `image-editor-upload.md`). A callback save (Workspace) has no Files: a message says so.
- Video from the site: `Editor.importItems` adds the item; `video_files` lists mp4, m4v and webm files as `sound_files` lists sounds (`pg_image_editor_sound_where()` and `_rows()` take the types).
- The clip goes to the bottom, fills the canvas (ratio kept), starts at the playhead and is selected; the document's length grows to its end (at most 60 s).

## What does the panel show?
- Select's panel (the type's `panel()`): the file (size, length), Source in, Source out, Start on the timeline (sliders), the speed chips, volume with mute (or "no sound"), Detach sound, Replace (another video from Files, its box keeps width and centre).
- Animate with one clip selected: tabs Video clip and Motion (the entrance, loop and exit as for any layer); the clip tab adds Duplicate and Delete. Draw on is off for a clip.

## What is left to later steps?
- Clip rows, trim, split, move and snapping came in V3 (`image-editor-clip-timeline.md`).
- Effects and transitions (V5); uploads in parts came in V8.
