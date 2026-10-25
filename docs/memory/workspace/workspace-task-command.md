---
name: workspace-task-command
description: The /task (/görev) command in the composer: Enter on its preview card creates the task (same text, card on screen, no leave clash), the / picker finds every Turkish and English name, /help is listed
---

## What did not work?
- Enter on the /görev card did nothing: the text went again without `confirm` and the same card came back; only a click on "Create the task" wrote, re-reading the box. `/gö`, `/tamam`, `/anket`, `/sor`, `/help`, `/yardım` found no command in the `/` picker although the server takes them (`ws_command_names()`).

## When does Enter create the task?
- `send()` (assets/js/workspace.js) keeps `pending = { body }` from a `preview` answer; `drawPreview()` adds `card`, `hard` and `confirm()`: the Create button's options (`confirm: 1`, the card's `assignees`) and `body`, the text the preview was drawn from; once it is created, a different draft in the box stays (`clearSent()`).
- Enter (or Send) creates with `pending.confirm()` only when the card is still on screen (`card.isConnected`), no hard clash holds it (leave, holiday: `check.hard`) and the text as `send()` posts it (`tokens(raw)`) equals `pending.body`. A changed text gets a fresh preview.
- A hard clash is never forced from Enter: only "Create it anyway" sends `force: 1` (shown to whoever `may_override`). The line "Enter: create · Esc: cancel" (`preview_keys`) is drawn only without a hard clash and not on a touch screen, where Enter starts a new line.
- The Create button also sends the previewed text, not what the box holds now. Esc (`cancelCompose()`), Cancel, a sent message and opening a channel set `pending` to null.
- The argument popup after "/görev " never takes Enter unless a row was picked with the arrows, and `send()` closes it, so Enter still reaches the preview (workspace-command-registry.md).
- /price and /stock cards are unchanged: Enter sends the text again and draws the card anew; only Save writes (workspace-record-commands.md).

## How does the / picker find a command?
- Each `ws_command_help()` entry, built from the registry `ws_commands()` (workspace-command-registry.md), carries `names`: every `ws_command_names()` key of its command with the slash, in table order (`ws_command_aliases()`). A `/help · /yardım` entry lists /help itself. The task label is `/task · /görev`; `ws_palette_commands()` reads the registry too.
- `openPicker('command')` matches every name, first in Turkish lower case as typed (`/gö` shows /görev), then folded with `foldText()` (ı→i, ö→o, ü→u, ş→s, ç→c, ğ→g: `/go`, `/YARDIM`). A row shows the matched name, the description and the example as a muted second line (`detail` in `drawPicker()`, `.ws-picker-detail`). With nothing typed each row shows the command's `name`, Turkish on a Turkish screen (/görev). A picked name opens the argument popup for its first argument.

## Where are the tests?
- `tests/workspace_commands_test.php`: `ws_command_parse_task()` (people, department, `~2sa`, `!acil`), day words (`cuma`, `+2g`), `/Görev` and `/YARDIM`, every name in a help entry. The composer was checked in headless Chromium on workspace.min.js with a stubbed API; no running instance.
