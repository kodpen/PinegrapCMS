---
name: share-links-screens
description: Screens of Share by link: the mark on a shared item, Share by link… and its dialog, the head's Sharing mark and menu, Channel info's Sharing, the system line, the sandbox check
---

## Where is the mark on an item?
- While a link to it is open: the link icon (`bi-link-45deg`) as a quiet round mark at the top right of the file card, the picture, the note card, a shared table or checklist (`share-links-table-list.md`) and on its Files tab tile or row; tooltip and screen-reader text "Shared by link until <date>". Everyone who reads the channel sees it; an ended or taken-back link leaves none.

## How is a link made on the screen?
- The item's menu, in an agent channel, for who may post: "Share by link…" (a table: "Share table by link…", a checklist: "Share checklist by link…"); with an open link "Stop sharing" in its place, for its maker and the channel's managers.
- A small dialog: the item's name, "Ends after" with 1 day, 7 days, 30 days (7 pressed), Create. Then the same dialog shows the address in a read-only field with Copy and "Shown once. Anyone with this address can read this item until <date>."; Done closes it.
- "Stop sharing" asks once ("The address stops working at once.").
- Code and payload: `share-links-item-menu.md`.

## What is the head's Sharing mark?
- A `bi-share` button in the channel head, tooltip "Sharing", only while the channel has an open link or a guest; under 40rem the ⋯ menu's entry "Sharing". Its menu: "This channel" (guests, or "Not shared outside") and "Shared by link" (a row per open link: kind icon, name, who, until, opens, Stop sharing for who may).
- Owner (2026-11-23): the channel's own sharing and its items' stand in this one place.
- Channel info has the same two parts under "Sharing", with "Nothing is shared outside this channel." when empty (`share-links-channel-sharing.md`).

## What does the conversation say?
- A system line when a link is made or taken back: "<name> shared <item> by link until <date>" / "<name> stopped sharing <item>"; an agent by its handle. No agent is woken, no bell line.

## What does a sandbox check cover?
- Workspace on, an agent channel, 1440 and 390 px, both themes: a picture, a file, a note, a table and a checklist shared; the mark on each and in Files; the head's mark coming and going; the address opened in a window with no session, after Stop sharing and after the end.
- Not run in the step PRs (no runnable instance); the pages and menus are covered by source tests.
