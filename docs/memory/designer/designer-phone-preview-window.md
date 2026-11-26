---
name: designer-phone-preview-window
description: The "Preview on a phone" window as built (PP2, #11578): its row in the Preview menu and the command palette, the part phone, its states, what it asks the server, the cost
---

## Where does it open from?
- The Preview menu's second row "Preview on a phone" (`#sd-vb-phone`), shown where "Share the prototype…" shows (`_pgPrototypeShareReady()`), and the command palette's "Preview on a phone" under the same rule. No button in any bar.
- Both call `_sdPhoneOpen()`, which runs the part `phone` (`assets/js/style_designer_phone.js`, `designer-parts-on-demand.md`) with a host from `_sdPhoneHost()`: the design, the page on screen (its id only when it is a saved draft, else the link starts on the first draft), `dirty()` over every page and `save(done)`, the main button's save.
- The part is handed only to roles 0-1 where share links work (`$sd_phone` in `includes/designer_screen.php`).

## What does it ask the server?
- `prototype_phone` with audience `anyone` on every open: the live link is found again, else made (`designer-phone-preview-server.md`). The answer carries `qr`, the inline SVG `pg_qr_svg()` drew from the address, and `link.start_page`. No image address, no copy in storage; the address lives in the part's state only while the window is open.
- `team_only` draws the lock state with "Make a team link", which asks again with audience `team`. "End now" sends `prototype_phone_end` and closes the window; a toast says "The phone preview has ended." (the drawing's line under the canvas bar is the designer's toast).

## Which states does it draw?
- `_phView(answer, secondsLeft)`: `ready`, `ended`, `team`, `failed`; `wait` while asking (spinner, "Making the link…", buttons off).
- `failed` draws "The link could not be made" over `_phFailure(answer)`: the server's `message` (e.g. "This design has no draft pages."), and "Try again" only for no answer, a malformed one ("Nothing was changed.") or an answer marked `retry` (the link could not be stored, the one such answer of `pg_proto_phone_panel()`). Its foot is hidden: no dead "Copy link" / "End now" (#11966; before, every failure read "Nothing was changed." with a useless "Try again").
- The time left is `_phLeftWords()`: "7 h 58 min", "8 h", "42 min", "less than a minute"; counted from the answer's `seconds_left` by a one-minute interval that also swaps the info line. At zero the code goes pale with "Make a new link".
- The info line: a page never saved names it ("… has not been saved yet"); otherwise unsaved changes give the amber line with "Save", which saves and redraws the line; else the blue line.
- A team link carries the chip "Team link" and "Sign in on the phone to open it."
- Below 768 px (`sd-phone-narrow`) it fills the screen: "Point another phone's camera here", "Open here" (new tab), "Copy link", "End now".
- "Share prototype" puts the chip "Phone preview" on the phone link's row (`l.phone`); its Revoke is unchanged.

## What does it cost?
- Core `style_designer.min.js` +997 bytes (+244 gzip): the row, the stub, the host, the palette row, the chip. The part is 8,707 bytes (3,268 gzip), fetched on the first press. While closed nothing runs: the interval and the dialog go with `hidden`.
- No canvas, edit or open path changed, so the probe measures (`designer-performance.md`) were not re-taken; no browser run in the session that built it.

Tests: `tests/designer_phone_window_test.php`, `tests/designer_preview_menu_test.php`, `tests/prototype_phone_test.php`.

Related: docs/memory/designer/designer-phone-preview-drawing-window.md, docs/memory/designer/designer-phone-preview-server.md
