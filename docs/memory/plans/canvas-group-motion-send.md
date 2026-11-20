---
name: canvas-group-motion-send
description: Plan (owner 2026-11-20): Artifact Canvas: a group recorded and shown as one, Attach to and Detach sound where the hand is, the main button as a split button with send and save choices; CS1 to CS5
priority: 7
---

## What is asked?
- Owner (2026-11-20, in the architect's window, with a picture of two grouped vector paths in Animate): "gruplama yapmama rağmen nesneler animasyonlanırken ayrı ayrı oluyor ve hala nesneyi nesne ile takip olmamış".
- "varsayılan düğmesi kanalda tuvali gönder ok ancak yanında ok olsun ve farklı kanala gönderi dosya olarak kaydet video olarak kaydet seçenekleri de olmalı. aynısı dosya yoneticide varsayılan görseli/videoyu kaydet ancak 3 noktada bir kanala tuval olarak gönder seçeneği".
- "videolarda ses ekle var ancak gömülü sesi olan videodan ses kopartma yok."

## What is there today?
- A group has its own keys, set on the Path tab or by a drag at a moment, laid over each member's own keys (`ui/image-editor-groups-look.md`). The tracks show members' keys only, and Record writes one layer's keys (`ui/image-editor-record.md`): what he recorded stayed two motions.
- Attach to exists (#10593, `ui/image-editor-attach.md`): the context menu and the Path tab of one layer. With a group picked neither shows it.
- Detach sound exists on the Select tool's clip panel only (`ui/image-editor-clip.md`); Add sound sits on the tracks.
- The main button is one action by caller (`ui/image-editor-callers.md`).

## What is built, and how is each part laid out?
- (C1) Record with a group picked writes the group's keys, one motion for all members. The tracks gain a group row above its members with the group's diamonds, dragged and deleted as a layer's.
- (C2) A picked group whose members hold path keys of their own: the Path tab says in one line "Members also move on their own paths" with a quiet button "Remove their paths" (one undo step).
- (C3) Attach to: also in the More menu of the bar over one object and first on a single layer's Path tab. With a group picked the Path tab says in one line how a layer follows another. The layers list marks a follower with a link icon and the target's name.
- (C4) Detach sound: on the clip's row menu on the tracks, on the bar over a picked clip and under Add sound as "Detach from a video". Refused off 1× with its reason in one line.
- (C5) The split button: the main button keeps its look; a narrow button with a chevron sits flush on its right (outer corners rounded, a hairline between) and opens the canvas's own menu.
  - From a channel: main "Send the canvas"; menu "Send to another channel…" (channels the person may post in), "Save to Files as a picture", "Save to Files as a video" (only with motion), "Download".
  - From the File Manager: main "Save" as today; the top bar's three-dot menu gains "Send to a channel as a canvas…" (only with the Workspace on).
- (C6) The split button joins the Design examples screen under buttons in the same PR (`workflow/screen-described-in-plan.md`).

## Which steps are there?
- CS1 (M): C1, C2. Tests under Node: a group's record gives group keys and none on members; the row's diamonds; the removal.
- CS2 (S): C3.
- CS3 (S) after CS2 (both enter the action bar): C4.
- CS4 (M) beside CS1: C5, C6; the rights of each choice tested. Done in #10858.
- CS5 (S) close-out: the `ui/image-editor-*` files corrected, this plan deleted.

## What binds it?
- No schema; `gfx`, `att` and `doc.sounds` keep their shapes. Members' own keys are never removed but by C2's button.
- `plans/canvas-data-views.md` (DV2) enters the canvas's add menu: the coordinator checks the open PRs' files before each step.
- Not run in a browser by a cloud session: the PR says what it could not see; the owner tries on dev.
