---
name: workspace-editor-pasted-files
description: A file pasted, dropped or picked into a Workspace writing box waits as a chip until Send: which box takes it, the chip and its remove button, words with the first file
---

## Which box takes a pasted file?
- `paste()` (assets/js/workspace_editor.js) asks `pastedFiles(files, text, anyFile)`: only a box made with `pasteFiles` takes files, and only when the clipboard holds no words (Word adds a picture of its text; the words are pasted then). It takes the pictures, or every file with `pasteAnyFile`.
- A note: `richField()` (workspace.js) passes `attach()`, so a pasted screenshot is attached like a dropped file. A note's attach is not a send and posts at once.
- The conversation's box (`composer()`, a channel's and a discussion's): `waitFiles()` with `pasteAnyFile`. The plain textarea fallback (no editor script) has its own paste listener calling `waitFiles()` too.
- Any other box drops pasted files; its words still paste.

## When does a file in the conversation's box go?
- With Send (or Enter), never at the paste. A file pasted, dropped on the conversation (`bindDrop()`, which opens the Messages tab first) or picked with "Attach a file" (from this device or from the file manager) waits in the box as a chip over the field.
- The chip (`drawWaiting()`, `.ws-wait-file`): a picture's thumbnail (an object URL) or a file icon, the name, the size (`sizeLabel()`) and a remove button (`dropWaiting()`).
- Send is live with words or a waiting file: the microphone gives way to Send while a file waits (`voiceSwap()`, `hasWaiting()`). `send()` hands the files to `sendWaiting()`, which posts them as `uploadFiles()` posts several: one message each, the box's words with the first, a reply bar's message answered.
- A file that fails waits again; Cancel on the upload card drops it and the files behind it. The box is emptied only while it still holds the words sent (`clearSent()`).
- Send later refuses while a file waits ("A file cannot be sent later…"); Arrow Up in an empty box does not change the last message then.
- A refusal the browser knows is said when the file is added, and the file is not added: over `BOOT.upload.max`, or a name the channel refuses (`uploadRefusal()` over `ws_upload_browser_rules()`, `workspace-upload-refusals.md`). The server checks again at Send.
- Waiting files live in the open screen only: not in the saved draft; another channel or discussion in the box drops them (`waitingFiles()`).

## Can the browser paste a picture into a box?
- No: `paste()` calls preventDefault on every paste with clipboard data, so no box shows a picture it cannot send (tests/workspace_editor_paste_test.php holds it).

## Why?
- Owner (2026-11-21, workspace decisions part 44, agent channel message 11680): a pasted screenshot posted at once with whatever words stood in the box, so messages with pictures had to be edited again. A pasted picture or file waits and goes with Send.
- Architect (2026-11-21): a dropped file and one picked with "Attach a file" wait the same way, so the box has one behaviour; no schema or API change, a message still carries one file.
- #9250 (earlier): the paste handler sat on the channel screen's centre, outside a discussion's panel, so the browser pasted the picture into the box itself; the box takes files itself since.

## How is it checked?
- tests/workspace_composer_files_test.php: under Node, a paste waits, Send posts the file with the words, the remove button, `uploadRefusal()`; the wiring of every door. tests/workspace_editor_paste_test.php: `pastedFiles()` and the paste wiring.
