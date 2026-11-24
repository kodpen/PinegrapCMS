---
name: image-editor-edit-label
description: Every button that opens the Artifact Canvas on an existing picture reads "Edit in Artifact Canvas" (owner 2026-11-24): the places, the tooltip on icon buttons, what keeps its own words
module: image_editor
---

## What does a button that opens the canvas on a picture say?
- "Edit in Artifact Canvas", Turkish "Yapıt Tuvalinde Düzenle": one key, `lang()` in PHP and panel scripts, `_sdT()` in the Page Design Studio (owner 2026-11-24, `workflow/decisions-made-panel-18.md`).
- An icon-only button keeps the icon and carries the words as `title` and `aria-label`; the live site's mascot button has them as `title` (with the file name) and as its image's `alt`.
- A button that makes a new canvas keeps its words: "New canvas", "New image", the File Manager's blank canvas, Workspace tables' "Show on a canvas".

## Which places carry it?
- File Manager (`view_folders.php`): the context menu's entry for a picture and for a video (`L.image_editor`), and the file window's tool button.
- Edit File and Edit Design File (text buttons), All Design Files' row (icon), the dashboard's heavy-images widget (icon), Translations' Pictures group (row button and the picture badges' tooltip).
- Page Design Studio (`style_designer.src.js`): the canvas and layers context menus' entries (were "Edit Image"), the image panel's icon button, the image and background picker buttons (icons).
- Workspace (`workspace.js`, `file_edit_image`): a message file's menu in the channel and in the thread, a note's canvas picture. Someone else's picture reads the same; the canvas's own save button still says "Edit and send".
- Live site edit mode: `add_edit_button_for_images()` (`includes/fn/content.php`) and the inline editor's button (`frontend.src.js` and its hand-patched `frontend.min.js`, key in `get_page_content.php`).
