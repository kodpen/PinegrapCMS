---
name: channel-share-links-screens
description: Screens of the share links plan: the mark on a shared item, Share by link in its menu and the dialog, the head's share mark with its menu, Channel info's Sharing part, the system line
---

## Where is the mark on an item?
- At the edge of the item's card (a file card, a picture, a note card, a table card) and of its Files tab row, while a link to it is open: the link icon (`bi-link-45deg`) as a quiet round mark at the card's top right, tooltip and screen-reader text "Shared by link until <date>".
- Shown to everyone who reads the channel. An ended or taken-back link leaves no mark.

## How is a link made on the screen?
- The item's menu, in an agent channel, for who may post: "Share by link…"; with an open link "Stop sharing" in its place.
- A small dialog: the item's name, the end as three choices (1 day, 7 days, 30 days; 7 chosen), Create.
- After Create the same dialog shows the address in a read-only field with Copy and the line "Shown once. Anyone with this address can read this item until <date>."; Done closes it.
- "Stop sharing" asks once ("The address stops working at once.") and takes the link back.

## What is the head's share mark?
- A button among `.ws-head-actions` (`workspace/workspace-channel-head.md`), icon `bi-share`, shown only while the channel has an open link or a guest; tooltip "Sharing". Under 40rem it is an entry of the ⋯ menu.
- It opens a menu of two parts. "This channel": the guests of the room with their count, or "Not shared outside". "Shared by link": a row per open link with the item's kind icon and name, who made it, "until <date>", "opened <n> times" or "not opened yet", and Stop sharing for who may.
- The channel's own sharing and its items' stand in this one place (owner, 2026-11-23).
- Channel info gets the same two parts under the heading "Sharing", there also when nothing is shared ("Nothing is shared outside this channel.").
- Rows, menu and dialog are the Design examples' patterns (list rows, a menu with headings, a small dialog); no new recipe.

## What does the conversation say?
- A system line when a link is made or taken back: "<name> shared <item> by link until <date>" / "<name> stopped sharing <item>". An agent is named by its handle. No agent is woken and no bell line is written, as for a memory change (`workspace/channel-memory-note.md`).
- An end that passes writes no line.

## What is checked?
- A sandbox with the Workspace on and an agent channel, 1440 and 390 px, both themes: a picture, a file and a note shared, the mark on the card and in Files, the head's mark appearing and going, the address opened in a window with no session, after Stop sharing, after the end.

Related: docs/memory/plans/channel-share-links.md
