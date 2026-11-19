---
name: designer-blocks-course
description: The course block family (course- prefix, Courses group): four hidden blocks filled by pg_course.js, the marks it reads, canvas and Preview rows, test
---

## Where does it live?
- `assets/js/style_designer_blocks_course.js`, prefix `course-`, palette group "Courses" (`bi-mortarboard`). Built for the courses plan (CO4, #10454); any site with the "Courses" switch on can place it.

## Which blocks does it hold?
- `course-done` "Mark as done": a real button (`data-pg-course-press`, `aria-pressed`) and a `role="status"` line (`data-pg-course-status`, hidden). Shown only on a lesson page to a member with the folder.
- `course-progress`: the course name (`data-pg-course-name`), a Bootstrap `.progress` with `role="progressbar"` (`data-pg-course-bar`) and its fill (`data-pg-course-fill`), the line "4 of 12 lessons done" (`data-pg-course-count`). Member only.
- `course-lessons`: the name, an `ol.list-group` (`data-pg-course-list`) whose first `li` (`data-pg-course-item`) is the row the script copies: a link (`data-pg-course-link`) and a badge (`data-pg-course-state`); "Continue" (`data-pg-course-next`, hidden). A visitor sees names only, no links or badges.
- `course-mine` "My courses": the same list as a `ul`, each course's count in its badge, and "You have no courses yet." (`data-pg-course-empty`, hidden). Signed-in members only.

## How does a block reach the page?
- Each root is a `section` with `data-pg-course="done|progress|lessons|mine"` and `hidden`: with the switch off, or no script, nothing shows (`catalog-binding-disabled-no-output.md`). The script and the door: `commerce/course-blocks.md`.
- No display utility on the hidden roots: a `d-*` class is `!important` and would beat `[hidden]`.
- Every text the script writes comes from the page's labels (`pg_course_blocks_labels()`), so the stored words are the editor's sample.

## What in style_designer.src.js serves it?
- `IFRAME_CSS`: `[data-pg-course][hidden] { display:block !important; }` draws the roots on the canvas; inner hidden parts stay hidden.
- `_SD_PAGE_SCRIPTS`: the Preview loads `assets/js/pg_course.js` (without the page's settings it leaves the blocks as they are).
- `_sdBlockSketch()`'s hidden check passes a `data-pg-course` root, like Listen and "Notify me", so the hover card draws it.

## How is it tested?
- `tests/designer_blocks_course_test.php` (Node): the group and four types; each block printed by `_render_tree_node()` as a hidden marked section with its marks, no script, handler, style or display utility; the canvas rule and the Preview row; the script's pure text helpers.
