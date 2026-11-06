---
name: decisions-made-product-31
description: Decisions list, product part 31 (2026-11-05 evening on): the image editor's move recorded by dragging, sound in an animation's video
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue, the date and the reason.

## Can a move be recorded by dragging a layer? (owner, 2026-11-05)
- Yes, beside the presets and the keyframes set one by one: while a layer is dragged in the Animate tool the clock runs in real time, and the path and its speed are kept as keyframes, so playback and every animation output repeat the same move. It works in the video plan's timeline too. Why: a move drawn by hand is quicker and more natural than keyframes set one by one. Built as AN12 (`ui/image-editor-record.md`).

## Does an animation's video carry sound? (owner, 2026-11-05)
- Yes: music and sounds from Files go on any canvas, not only under a video, and an MP4 or WebM export carries them; GIF, APNG, WebP and PDF cannot hold sound. The video plan's V4 and V7 need no video layer, so they may come right after its screens. Why: an animated post or clip is shared with music. Plan: `plans/image-editor-video-steps.md`.
