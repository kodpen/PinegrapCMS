---
name: workspace-editor-pasted-files
description: A picture or file pasted into a Workspace writing box (#9250): which box takes it, words beside it, why the box takes it itself
---

## Which box takes a pasted file?
- `paste()` (assets/js/workspace_editor.js) asks `pastedFiles(files, text, anyFile)`: only a box made with `pasteFiles` takes files, and only when the clipboard holds no words (Word adds a picture of its text; the words are pasted then). It takes the pictures, or every file with `pasteAnyFile`.
- A note: `richField()` (workspace.js) passes `attach()`, so a pasted screenshot is attached like a dropped file.
- The conversation's box (`composer()`, a channel's and a discussion's): `uploadFiles()` with `pasteAnyFile`, posted as "Add file" posts it, the box's words with it. The plain textarea fallback (no editor script) has its own paste listener doing the same.
- Any other box drops pasted files; its words still paste.

## Can the browser paste a picture into a box?
- No: `paste()` calls preventDefault on every paste with clipboard data, so no box shows a picture it cannot send (tests/workspace_editor_paste_test.php holds it).

## Why does the box take files itself (#9250)?
- A screen clipping (a bitmap, no words) pasted in a discussion was lost: nothing on an empty box; beside words the picture showed in the box and the message went without it.
- The paste handler sat on the channel screen's centre (`bindDrop()`). A discussion's panel (`makeThreadView()`) hangs off the screen's root, outside the centre, and `paste()` left files to that handler, so the browser pasted the picture into the box itself. The centre's paste handler is gone; dropping files onto the conversation still uses `bindDrop()`.

## How was it checked?
- tests/workspace_editor_paste_test.php: `pastedFiles()` under Node and the wiring. Headless Chromium on the editor alone: a synthetic picture or file paste into a box with and without `pasteFiles` (taken, default prevented; the old code left it to the browser). A real clipboard in the full screen was not tried.
