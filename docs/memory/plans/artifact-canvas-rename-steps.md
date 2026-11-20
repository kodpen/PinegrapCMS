---
name: artifact-canvas-rename-steps
description: Plan artifact-canvas-rename, steps AC1 to AC5: the new drawing, the name in every text, memory's label, the canvas's icon on the buttons that open it, close-out
---

## Which steps are there?
- AC1 (S) The drawing. Done: #10516 (`pg_menu_icon()`, `tests/design_canvas_icon_test.php`, `ui/design-canvas-icon.md`).

- AC2 (M) The name; may run beside AC1. Cut in two: AC2a (done, #10517) and AC2b (done, #PR).
  - AC2a, done (#10517): the panel files of the list below but the held ones, their unused old keys removed; `tests/artifact_canvas_name_test.php`.
  - AC2b, done (#PR): `includes/fn/image_editor.php`, `assets/js/style_designer.src.js`, `get_page_content.php` with both `frontend` twins; every `Design Canvas` key removed; in `tests/artifact_canvas_name_test.php`. Comments in files AC2a did not touch go with AC5.
  - Every text with "Design Canvas" (`grep -rn 'Design Canvas' pinegrap tests`): `lang()`, `_sdT()`, the `lang(array('string' => …))` forms, the plain strings of `includes/fn/system_status.php`. Turkish with `php tools/tr_add.php` (`core/lang-files.md`).
  - Files: `view_folders.php` (File Manager), `includes/workspace/file_edits.php` (channels), `assets/js/style_designer.src.js`, `includes/fn/image_editor.php`, `includes/fn/content.php`, `translations.php`, `includes/settings/features.php`, `edit_file.php`, `edit_design_file.php`, `includes/fn/design_file_list_edit.php`, `includes/dashboard/widgets/widget_18.php`.
  - `get_page_content.php` and `frontend.src.js` with its hand-edited twin change together (the script asks for a text by its English) and are held whole while another PR changes the twin (`workflow/held-file-bundled-sources.md`): a part b then.
  - Memory: `designer/editor-turkish-terms.md`.

- AC3 (S) after AC2, docs only: memory's label.
  - Each memory file that names the screen says Artifact Canvas (`grep -rl 'Design Canvas' docs/memory`, about 130 files). File names and code names stay, the owner's quoted words stay, a running plan's files are left to its own close-out.

- AC4 (M) after AC1 and AC2: the canvas's own icon, in its one-colour form (owner 2026-11-13, #9250), on the buttons that open it with another icon today.
  - Cut in two by held files: AC4a (done, #10536) and AC4b.
  - AC4a, done (#10536): the canvas card's head, a guest's waiting canvas, `edit_design_file.php`, `edit_file.php`, `design_file_list_edit.php`, widget 18; pinned in `tests/design_canvas_icon_test.php`.
  - AC4b, done (#10646): the places below not done by AC4a; also both message menus' "Edit image" in Channels; pinned in `tests/design_canvas_icon_test.php`. No place left for a part c.
  - File Manager (`view_folders.php`): "New image" in the New menu and the context menu (`bi-easel`).
  - Channels: the canvas card's head (`workspace_page_card.js`, `bi-easel2`), a guest's waiting canvas (`workspace_guest.js`, `bi-easel`), "Edit the picture" on a note's picture (`workspace.js`, `bi-pencil`).
  - Page Design Studio (`style_designer.src.js`): the three `bi-brush` buttons (a content operator's Image row, the image address field, the background image field) and the two "Edit Image" menu entries; their titles say "Edit the image in the Artifact Canvas". The markup comes from the canvas's config, beside `brandIcon`.
  - Panel: `edit_design_file.php` and `edit_file.php` (`bi-flower2`), `includes/fn/design_file_list_edit.php`, the dashboard's widget 18 and the Translations screen's "Edit in the Design Canvas" (`bi-brush`).
  - Left as they are: a button that does not open the canvas, the Translations screen's picture chip (`bi-image`), a design block's own `bi-easel`.
  - No pictures to the owner: the step goes ready when green.

- AC5 (S) close-out: every place of AC2 and AC4 checked against the code with its PR; the topic files of `plans/artifact-canvas-rename.md` say the new state; the pinegrap.com pages that name the canvas are named for who writes them (`workflow/user-docs-pages.md`); the plan's files deleted.
