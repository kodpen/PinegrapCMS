---
name: workspace-flows-thread-removing
description: Removing a data thread on the Flows canvas (#4057, #4127, #4275): hit path, selection, Delete, menus, the chip's × and its space, the outline's Remove; deleting a box removes its values (#4058)
---

## What does removing a thread do?
- A thread is only its token in the field's text, so removing it is `unbindThread(to, key, token)`: `withoutToken()` takes every place the field reads that value (with or without a fallback) and the one space that set it apart (`tokenGap()`: the one before when a space, line break, punctuation or the end follows, else the one after at a line's start); the rest of the text stays. A condition's `rule.ref` becomes ''. Test `…_thread_is_removed_from_its_field`.
- Through `removeSelected({ type: 'thread', id })`: one structural `changed(true)`, so one Undo brings it back; announced "Removed: <value> → <box> › <field>" (`threadName()`).

## How is a thread selected?
- `threadHit()`, from `drawData()` and `goneThread()` in a draft: a transparent 10 px path on the same route, class `wsf-thread wsf-thread-hit` (hidden with the toggle, dimmed with the rest), `data-thread` = `threadKey()` (`<to> <field> <token>`). Its pointer events, stroke and cursor are inline styles: `.wsf-thread` in backend.src.css says `pointer-events: none`. Hover draws the thread 2.5 px; selected, 2.5 px in `--ws-accent`.
- `pointerDown()` selects `{ type: 'thread' }`; Delete / Backspace removes it; the side panel (`threadForm()`) says what it brings where and has "Remove the thread". Arrow keys move only a box. Hiding the threads unselects one.

## Where else can a thread be removed?
- A value dot's menu and a field chip's menu (`threadRemovals()`): "Remove the thread", or with several "Remove the thread: <box › field>" (dot) / "<box › value>" (chip).
- The side panel's chip: a × (`.wsf-chip-remove`, aria-hidden, inline style) in a draft, also on a red chip; `valuesText()` reads only the token. It removes that one chip and, by `tokenGap()` on the text nodes beside it, the space that set it apart, announced "Removed: <value> → <box> › <field>" with the field's caption (PR #4275, #4236: it left "Bildirim , tamam" and named only the value). A click elsewhere on a known chip still asks "When empty, write…".
- The outline's "<field> reads <value>" line: a Remove button (aria-label names the value) for the keyboard and screen readers; the focus goes to the box's title, or to its outline line (`outline-open-<id>`) when the box's settings are closed and no title is drawn (PR #4275, #4235: it fell to the body).

## What does deleting a box do? (#4058)
- `removeSelected()` calls `dropValuesOf(id)`: every field of the other boxes loses the values read from that box (same rules as above), then the box and its arrows go, all in one undo step. Announced `fl_removed_values(_one)`: "Removed: X; its values were removed from 3 fields". Test `…_deleting_a_box_removes_its_values`. Owner decision: `workflow/decisions-made-workspace-5.md`.
- Before, the fields kept the tokens: red dashed threads from the box's old place (`state.gone`, removed), red chips and a `bad_reference` per value that blocked publishing.
