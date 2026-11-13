---
name: composer-picture-paste
description: Plan (owner 2026-11-13, #9250): a picture pasted into a Workspace writing box is posted with or without words, and nothing the box shows is dropped on send; one step, CP1
---

## What is asked?
- Owner (2026-11-13), in a discussion of a channel: "kopyalanan bir görsel kanala yapışıyor ancak hiç kelime yazılmamışsa yapışmıyor." and "görsel sohbet içinde vardı ama mesajla gitmedi bu arada". The picture was a screen clipping: the clipboard holds a bitmap and no words. "Add file" posted the same picture.
- What must hold after it is the architect's decision, a technical one (#9250).

## What was seen?
- An empty writing box, a clipping pasted: nothing happens.
- Words typed, a clipping pasted: the picture shows in the box; the message is sent without it.
- Seen in a discussion (a thread channel) on dev. Whether a channel's own box does the same is the step's first check.

## What does the code say?
- The box's paste handler (`paste()`, `workspace_editor.js`) returns without acting when the clipboard holds files and the box has no `pasteFiles` (only a note's box has one): files are left to the conversation's own handler.
- That handler (`bindDrop()`, `workspace.js`) takes a paste inside `.ws-composer` and posts the files at once through `uploadFiles()`, the box's words going with the first file.
- By that reading a pasted picture never stays in the box. It did. So the conversation's handler did not act, and the browser's own paste put a picture into the box, which the box does not send. The architect did not reproduce it: the cause is the step's to find.

## What must hold after the step?
- A picture pasted into the writing box of a channel or a discussion is posted as "Add file" posts it, with or without words in the box; the words go with it as today.
- Nothing the box shows is dropped silently on send: the browser's own paste never leaves a picture in a box that cannot send it.
- A note's box keeps its own way (`pasteFiles`). Pasted words, HTML and sheet ranges stay as `workspace/workspace-editor-smart-paste.md` says.

## Which steps are there?
- CP1 (S) A pasted picture is posted. Claim as `[plan:composer-picture-paste] CP1 — A pasted picture is posted`.
  - Reproduce in a browser with a clipping (a bitmap, no words), in a channel and in a discussion; the cause goes into the PR body.
  - A test where a function can be reached; else the browser run, named in the PR with why no unit test reaches the path.
  - `php tools/build_js.php` after the scripts. `workspace/workspace-editor-smart-paste.md` gains the rule; this plan is deleted.
