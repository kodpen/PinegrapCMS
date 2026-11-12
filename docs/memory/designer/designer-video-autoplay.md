---
name: designer-video-autoplay
description: Play by itself on a Video item (B9): the autoplay interaction, muted and looping, its pause button (data-pg-video-toggle, pg-video-toggle rule), still under reduced motion and without script
module: designer_fx
---

## How does a video play by itself?
- Video panel > Play by itself (`_sdVideoSetAutoplay()`, PR #8831), the only way: the owner's terms of 2026-11-11 ([[decisions-made-product-44]]). No `autoplay` attribute is ever written; the switch removes one.
- On: `props._fx = {effect: 'autoplay'}` (an interaction with no field, `pg_fx_normalize()` and `_sdFxNormalize()`), `muted`, `loop`, `playsinline`, no `controls`, an id (`pg-video-xxxxxx` when it has none), the box placed as for Fill its box (`_sdVideoFillHostClass()`), the `pg-video-toggle` rule added to the User Styles.
- Off: the interaction and the pause button go; the controls come back unless the video fills its box; muted and loop stay.
- The Interactions list shows "Play by itself" only on a video that has it, with a note that the Video panel turns it on and off.

## What is the pause button?
- Put right after the video in its box, once: `button.btn.btn-secondary.btn-sm.rounded-circle.position-absolute.bottom-0.end-0.m-3.z-1.pg-video-toggle`, `type="button"`, `data-pg-video-toggle`, `aria-controls` = the video's id, `aria-pressed="false"`; a `bi-pause-fill` icon (`pg-video-pause`), a `bi-play-fill` icon (`pg-video-play`), both `aria-hidden`, and a visually hidden "Pause video". Layer name "Pause Button".
- A video whose box is the page itself or a placed component gets no button.
- Rule `pg-video-toggle` (`_SD_BLOCK_CSS`): the play icon only while `aria-pressed="true"`; the button hidden (class written twice to win over `.btn`; block rules never use `!important`) under `prefers-reduced-motion: reduce` or `scripting: none`, where the video cannot play by itself.

## What does pg_fx.src.js do with it?
- `videoStart()`: only on a `<video>`; sets muted, loop and playsInline, then plays, or pauses under reduced motion. A refused `play()` leaves it paused and the button pressed.
- A click on `[data-pg-video-toggle]` (captured on `document`) finds the video by `aria-controls`, else the nearest `video[data-pg-fx="autoplay"]` in the boxes around it, and toggles it; every button of that video gets the new `aria-pressed`. The visitor's own press plays it under reduced motion too.
- Reduced motion turned on while the page is open pauses every autoplay video (`still()`).
- The page gets the script because the video carries `data-pg-fx` (`pg_fx_inject()`); no new platform script.

## Where is it tested?
- `tests/designer_fx_test.php`: normalizer and attributes (also the editor twins), the script on a fake document (`test_designer_fx_script_autoplay_video`).
- `tests/designer_video_item_test.php` `test_designer_video_item_play_by_itself`: the switch's tree changes, the panel, the rule.
