---
name: guest-page-content
description: What a guest sees of what agents and canvases put in a room (GP1-GP3): role and model, files, a canvas as its picture, task cards, a live page card in the guarded frame; read only
---

## What does a guest see?
- Owner decision: `workflow/decisions-made-workspace-33.md`. Everything is read only; a guest's own abilities did not change, and nothing a guest does asks an agent (`guest-agent-lines.md`).
- Beside an agent's name: the badge, its role, its model, muted (GP1, #9693).
- An agent's file or picture as a person's; a canvas as its drawn picture, or "The preview is not ready yet." while pending; an agent's task card (GP2, #9717).
- An agent's live page as a card: name, "Live page by @handle. It is the agent's work, not the site's.", Open page and Full screen (GP3). No View source, versions, Undo or Download.
- Unchanged (owner: "Yalnız türü, bugünkü gibi"): a record's tag reads as its kind; mail cards, change cards, buttons, request lines and other applications' lines stay hidden.

## Who decides what a guest reaches?
- `ws_guest_file_reached()` (pure): the file of a person's, an agent's or an MCP answer's line, in the room, after the clear floor (`workspace-guest-cleared-room.md`), not deleted, never a live page. Payload and `workspace_guest.php?file=<id>` both ask it.
- `ws_guest_page_reached()` (pure): the same reach for an application agent's line whose file is a page (`ws_page_is_page()`, an `.html` name) of 1 byte to 500 KB (`PG_GUARDED_FRAME_MAX_BYTES`). A person's, a guest's or another application's line never runs.
- Tests: `tests/workspace_guest_cleared_room_test.php` holds a refusal test for each: hidden application, another channel, an earlier conversation, a deleted line, no page, over the bound.

## How does a live page run for a guest?
- `workspace_guest.php?page=<id>` (`ws_guest_page_send()`): the guest's cookie (`ws_guest_context()`), a frame's request only (`pg_guarded_frame_request_framed()`), then the reach above; anything else the same 404 line as `live_page.php`. The page goes out with the site's libraries through `pg_guarded_frame_send()` in script mode, its own headers replacing the guest page's `X-Frame-Options: DENY` with SAMEORIGIN.
- `live_page.php` stays for panel sessions only. A canvas's page part (`ws_canvas_part_message()`) is not served to a guest: a canvas is its drawing.
- `workspace_guest.js` `drawPage()`: the frame with `pg_guarded_frame_panel_sandbox('script')` is made on Open and removed on Close; its height is taken only from a frame the card made, bounded to 120-640 px; in Full screen the agent's line stands above the frame.
- The data bridge (`canvas-bridge.md`) has no answer on the guest page: a page asking for rows gets none.

## What happens when the conversation changes while a page is open?
- `drawMessages()` keeps the row of an open page in place and draws the other rows around it, so the page does not start again; that row's reactions refresh only after Close. A page whose line is gone is closed.

## What was not verified?
- The page in a real browser frame on dev.pinegrap.com; the sandbox run is described in the GP3 PR.
