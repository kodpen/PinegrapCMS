---
name: workspace-channel-composer
description: The channel's writing box in one row (C5 of workspace-channel-screen.md): no hint line, marks behind Aa; on a phone the field on top and the tools with Send under it, the keyboard rules
---

## What is the row on a wide screen?
- `composer()` (workspace.js): the field, then one row of attach, @, #, emoji, insert, /, Aa (and Pinegrap AI's rewrite), an empty `.ws-composer-hint` that grows, the count, Send (with the scheduled-send clock) or the microphone.
- The hint line ("@ someone · # a record …") is gone; the span stays empty and the expanded box writes its "Ctrl+Enter to send" there (`workspace_editor.js` `expanded()`).
- Bold, italic, lists and undo (`.ws-composer-format`) show only in the expanded box ("Aa"); the bar over a selection still formats everywhere. The container queries that hid them by width are gone.

## What is it on a phone?
- Below 768px the box is laid out as on a wide screen (#10495): the field takes the full width on top (16px, at least 2.5rem), the row of tools with Send (or the microphone) under it. No switch hides the tools; the plus switch (`.ws-composer-more`, `.is-tools-open`) of the earlier one-row phone box is gone.
- Send is never cut at 375 px or in the narrow discussion panel (#3941): `.ws-composer-tools` wraps everywhere, and on a phone the tools' pads narrow to .4rem and Send's `px-5` to .6rem, so the inserts, Send and Send later share one line at 375 px. Test: `tests/workspace_composer_phone_test.php`.
- While the keyboard is up (`body.ws-keyboard`, `bindFit()`), the catch-up and pinned strip and the tabs hide too: the conversation keeps the height between the header and the box.
- On an iPhone 13 viewport (390 × 664) the message list measured 415 px of 664 with the keyboard down and 222 of 385 with it up, with the earlier one-row box; the row of tools now under the field takes one line more.

## Why?
- Owner (2026-11-20, `workflow/decisions-made-product-80.md`): on a phone the field on top and the tools row with Send under it, as on a wide screen, replacing the one row with the tools switch.
- Owner (2026-10-31, `workflow/decisions-made-workspace-5.md`): on a phone the box has to ride on the keyboard and the messages keep the screen; this stays.

## Why does a menu of the box stay open when the phone's keyboard goes down?
- A tool of the box (attach, emoji, insert and its table picker, send later) calls `ctxFollow(button)` before `ctxMenu()` / `floatBox()`; the menu then stands at the same offset from that button. Opening it takes the focus from the field, the keyboard goes down and the window grows (#10256; the owner met it on a phone, 2026-11-17: the menu flashed and closed).
- A resize that keeps the width, a visual viewport change or a scroll re-places such a menu (`ctxAfterChange()` says place or close, `ctxPlace()` reads the button's new place a moment later, after `bindFit()`); a new width (a turn of the phone) closes it. A choice, a tap outside or Esc closes it, and the focus goes back as before.
- Every other menu (a message's row, a header) still closes on a resize or a scroll of its list. Test: `tests/workspace_composer_menu_test.php`.
