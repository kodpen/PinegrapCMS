---
name: designer-note-author-stamping
description: Node notes get their author stamped in pg_designer_save_page via pg_stamp_note_authors, not in save_system_style; unchanged notes keep their stamp.
---

## Where is a note's author stamped?
- `pg_stamp_note_authors(&$tree, $page_id, $user)` (functions.php) is called inside `pg_designer_save_page()` **after the merge and before `json_encode`**.
- **No stamping in `save_system_style()`.** That function's scope has neither `$page_id` nor `$user` (only `$user_id`), and the multi-page editor sends it no tree (the tree belongs to the page). Stamping written there silently did nothing; symptom: "no author name on the note".
- A note whose text is **unchanged** keeps its old stamp; otherwise whoever saves the page would take over every note on it.
- `_notes_by` sent by the browser is never copied on any path.
