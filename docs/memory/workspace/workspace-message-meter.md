---
name: workspace-message-meter
description: The Workspace composer's length meter near WS_MESSAGE_MAX: what is counted, the limit's source, 90 % and over, the send refused early, commands, screen readers, boxes
---

## What is counted?
- `messageMeter(text, max, commands)` (`assets/js/workspace.js`, pure) counts what the server counts: the body as posted (`this.tokens(this.inputText())`, chips as tokens), trimmed of the blanks PHP `trim()` takes (not a no-break space), in code points like `mb_strlen()` (a surrogate pair is one; an emoji with a joiner is several, as on the server).
- `ws_tokens_normalise()` may drop a broken token on the server; the box never makes one, so the counts agree.

## Where does the limit come from?
- `WS_MESSAGE_MAX` (`includes/workspace/messages.php`, 4000) goes to the script as `message_max` in `ws_screen_config()`; the script never holds the number.
- Server checks it guards: `ws_message_send_check()`, `ws_message_edit` (same limit), `ws_scheduled_message_save()`.

## What does it show?
- Nothing below `METER_FROM` (90 %, rounded up); from there `n / max` before Send (`.ws-composer-meter`); past the limit the danger colour (`.is-over`).
- `drawMeter()` runs from `autosize()`, so on every input, clear, restore and edit start.
- A hidden `aria-live="polite"` region beside it speaks only when the state changes (appears, crosses the limit, goes back): "n characters left", "n characters over the limit of max". Not on every key.

## When is the send refused?
- `meterRefuses()` in `send()` and `scheduleSend()`: past the limit it toasts the server's own words (`msg_too_long`, the existing key) and posts nothing; the server still refuses on its own, and the send path's error handling is unchanged.
- A text starting with a single `/` is read by `ws_command_run()` before the limit (`ws_send`), so the meter warns but leaves it to the server; `//` posts the text and is refused. A message or scheduled message being changed runs no command and is refused.
- A card's button (`send(options)`) sends its own text and is not checked.

## Which boxes?
- The channel composer and the discussion panel's (`makeThreadView()` shares `composer()`), new and changed messages, scheduled ones. Not notes, the task drawer or other rich fields: they post no message body.

## How is it tested?
- tests/workspace_message_meter_test.php runs `messageMeter()` under Node (emoji, Turkish letters, blanks, 90 %, the limit, one over, commands) and holds the wiring. The look of the meter and what a screen reader says were not checked in a browser.
