---
name: workspace-channel-composer
description: The channel's writing box in one row (C5 of workspace-channel-screen.md): no hint line, marks behind Aa; a phone's one row with the tools switch, the keyboard rules
---

## What is the row on a wide screen?
- `composer()` (workspace.js): the field, then one row of attach, @, #, emoji, insert, /, Aa (and Pinegrap AI's rewrite), an empty `.ws-composer-hint` that grows, the count, Send (with the scheduled-send clock) or the microphone.
- The hint line ("@ someone · # a record …") is gone; the span stays empty and the expanded box writes its "Ctrl+Enter to send" there (`workspace_editor.js` `expanded()`).
- Bold, italic, lists and undo (`.ws-composer-format`) show only in the expanded box ("Aa"); the bar over a selection still formats everywhere. The container queries that hid them by width are gone.

## What is it on a phone?
- One row below 768px: `.ws-composer-more` (a plus, `aria-expanded`), the field (16px, at least 2.5rem), Send or the microphone. The switch puts `.is-tools-open` on the box: the row of tools comes under the field, Send with it; the plus turns into a cross. `.ws-composer-tools` wraps everywhere and Send's `px-5` narrows to .6rem on a phone, so Send later is never cut at 375 px or in the narrow discussion panel (#3941).
- While the keyboard is up (`body.ws-keyboard`, `bindFit()`), the catch-up and pinned strip and the tabs hide too: the conversation keeps the height between the header and the box.
- On an iPhone 13 viewport (390 × 664) the message list measured 415 px of 664 with the keyboard down (240 before C1) and 222 of 385 with it up (50 before).

## Why?
- Owner (2026-10-31, `workflow/decisions-made-workspace-5.md`): on a phone the box has to ride on the keyboard and the messages keep the screen.
