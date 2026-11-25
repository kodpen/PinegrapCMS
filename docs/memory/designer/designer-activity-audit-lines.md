---
name: designer-activity-audit-lines
description: How the Activity tab's reader turns the audit trail's design, shared component and page-move lines into sentences (AC5), and who sees them
module: designer_activity
---

## How does the reader turn a line into a sentence?
- `pg_designer_activity_from_audit($row, $context)` (`style_id`, `draft_folder`). Changed fields come from both sides (`pg_designer_activity_line_fields()`; a side too large keeps `fields`).
- Page `update`: into the drafts folder `page_unpublish` "took X off the site", out of it `page_publish` "put X on the site"; any other page edit is no event. No drafts folder: none read.
- Style (kind `design`): `style_save`, `style_rename` (only `style_name` changed; the label is the new name), `style_create`, `style_copy` (a copy's line in its original's list: "duplicated the design as X"), `style_recycle`, `style_delete`.
- Shared component (kind `component`): `component_create`, `component_save` (`tree_json` changed), `component_rename` (only `name`), `component_settings` (anything else), `component_delete`.
- A line with source `job` names "The schedule" as the person (`user_id` 0).

## Who sees which line?
- The designer roles: every line of the design, the copies made from it, and every design file and shared component line (both are the site's, not one design's, like the design files before).
- A content editor: page lines of the pages that are theirs, no design-level line (`page_id` 0 fails `pg_designer_activity_visible()`).
- A line is shown while the trail keeps it; without the trail's table (`pg_audit_ready()`) the tab shows the other stores' rows only.

## Tests?
- `tests/designer_activity_test.php`: the page moves, the design and component actions, the copy in both lists, the job's person, the changed-field names, every new sentence.


Related: docs/memory/designer/designer-activity-audit-trail.md, docs/memory/designer/designer-activity-reader.md
