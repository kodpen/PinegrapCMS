---
name: designer-video-item
description: Palette > Content > Video: a <video> with controls, no autoplay; its panel (file from the Files tab, poster, switches), the "Fill its box" switch for a hero, what a player holds.
---

## What does the Video item add?
- Palette > Content > Video (#8744): `<video class="d-block w-100 h-auto" controls preload="metadata" playsinline>`, layer name "Video". No `autoplay`: only the hero video of step B9 plays by itself ([[designer-block-motion]]).
- `preload="metadata"` loads the first frame and the length, not the film; `playsinline` keeps it in the page on an iPhone.

## Where are its file and poster picked?
- The Video panel (`propsVideoOptions()`, shown for every `<video>` node). Video file > Select opens the file manager picker on its Files tab: `sdOpenFilePicker(field, prop, 'file')` adds `&type=file`. The picker keeps that tab for the session, as a click on the tab does. A pick writes the file's address (`/film.mp4`).
- Poster image > Select opens the image picker (`sdOpenImagePicker`). Title, the Controls, Loop and Muted switches and Preload (Browser default, None, Metadata only, Whole file) write the attributes.
- A src or poster bound to a record field (in a widget, [[bound-media-src]]) shows "From a record field" instead of its field; it is edited in the Data panel.

## How does a video fill a hero?
- "Fill its box" adds `position-absolute top-0 start-0 w-100 h-100 object-fit-cover z-n1`. A class of the same family (position, inset, width, height, object-fit, z) gives way: `h-auto` would beat `h-100`. Off gives back `w-100 h-auto`.
- It also turns Controls off, and the Controls row hides while the fill is on: the box's content covers the player.
- The box it sits in (semantic, container, row or column) gets `position-relative` unless a position, `fixed-*` or `sticky-*` class places it, and `z-0` unless it has a z class. The box becomes the video's stacking context, so the video paints above the box's background and under its content. Turning the fill off leaves those two classes.
- Full-Screen Media hero (B1 `spotlight-media`): drop the Video into the section beside its overlay and turn on Fill its box.

## What may a player hold?
- `canDrop()` Rule 7: inside `<video>` or `<audio>` only `<source>` and `<track>`. Anything else is fallback content that no current browser shows, so a heading dropped there vanished.

## Where is it tested?
- `tests/designer_video_item_test.php`: the palette item, the picker tab, the fill helpers, the panel. `tests/designer_inline_drop_test.php`: Rule 7.
