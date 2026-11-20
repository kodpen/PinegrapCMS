---
name: prototype-viewer-missed-click
description: The prototype viewer's missed click (PX6): a click on nothing clickable flashes the page's links in the frame; when it does not; Play's start page and its title
module: prototype
---

## What does a missed click do?
- Architect (2026-11-19, plan prototype-next): "Play: a click that misses flashes the clickable places." Drawn in the viewer's frame (designer/designer-prototype-viewer.md).
- (PX6) The frame's helper script (`pg_proto_frame_inject()`, `miss()`) adds `pg-proto-flash` to the page's root for 0.9 s: the links that lead somewhere (`a[data-pg-proto]` but `broken` and `draft`) get the Hotspots blue outline and fill, which fades out. With reduced motion the outline shows still for that time.
- The same frame serves a share link, so a guest gets it too.

## When does it not flash?
- A click on or inside a link, button, field, label, summary, video, audio, `[role=button|link|tab]`, `[onclick]` or `[contenteditable]`; a button other than the main one; while Hotspots is on (all is outlined already); while text is selected; on a page with no link that leads somewhere; in comment mode (the click places a comment).
- A page script's own click handler on a plain element is not seen: such a click still flashes.

## Where does Play start?
- The canvas bar's Play (designer/prototype-list.md) opens the viewer at the selected page when it has an address (`_pgPrototypeUrl()`: a saved draft tab), else at the first tab that has one (`_sdProtoPlayStart()`).
- (PX6) Its title and accessible name say which: "Play the prototype from {page}", so a selected page that is unsaved or not a draft does not hide that Play starts elsewhere. Updated on each change of selection (`_sdProtoPlayState()`).

## How is it checked?
- `tests/prototype_viewer_test.php`: `test_prototype_frame_inject_flashes_the_links_on_a_missed_click` runs the frame script in Node over a stub document (plain click, button, link, right button, Hotspots on, text selected, no links). `tests/proto_hotspots_test.php`: `test_proto_hotspots_view_play_names_its_start`.
- Not tried in a browser when it was built.
