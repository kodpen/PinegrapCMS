---
name: image-editor-detach-sound
description: Artifact Canvas Detach sound where the hand is: the bar over a clip, a clip row's menu on the tracks, Detach from a video under Add sound; the reasons it is refused
module: image_editor
---

## What does Detach sound do?
- It adds the clip's sound as an entry of `doc.sounds` on the same file and times and mutes the clip, one undo step (`M.clipDetach()`, `image-editor-clip.md`). No schema; `doc.sounds` keeps its shape.
- Owner (2026-11-20): "videolarda ses ekle var ancak gömülü sesi olan videodan ses kopartma yok": it sat on the Select tool's clip panel only. #11015 put it where the hand is.

## Where is it offered?
- The bar over one clip in the Animate tool, after Split clip, when the clip has a sound track (`M.actionList()`, state `sound`; command `detachSound`, label `clipDetach`). The phone's bar is unchanged (approved list).
- A clip's row on the tracks: a right click or the menu key on a bar or the row's name opens the context menu for that clip (`menuPlace()` reads `data-clip`: a bar's own clip, else the row's selected one, else its top one), selects it, and starts with Split clip and Detach sound (no sound track: Split clip only; a locked clip: both off), then the stage's items (`s.clipRow`). A long press there does not open it: the Animate tool's press drags.
- Under Add sound, as "Detach from a video": a row on the sound rows and a button in the panel's Sounds list (the phone's way), while a clip is unlocked with a sound track (`M.clipDetachList()`). The selected clip or the only one is detached at once; several are named in a menu as their bars are (`clipMenuName()`, menu place `detachFrom`).
- The Select tool's clip panel keeps its Detach sound tile (disabled off 1×, locked or without sound).

## When is it refused?
- `M.clipDetachWhy(l, count)`: `locked`, `sound` (no sound track), `speed` (off 1×: the sound would not match the pictures), `full` (20 sounds, `SOUND_MAX`). `clipDetachHere()` says the reason in the toast, one line, as an alert: "Unlock the clip to detach its sound.", "This video has no sound.", "The sound is detached only at 1× speed: set the clip's speed to 1× first.", "This canvas already holds 20 sounds: delete one to detach another."

## How is it checked?
- `tests/image_editor_detach_place_test.php` under Node: the reasons, the bar's list, a row's menu, the button under Add sound on the rows and in the list, one clip at once and several in a menu. Not run in a browser: the menu on a real track row, the toast, the rows' look.
