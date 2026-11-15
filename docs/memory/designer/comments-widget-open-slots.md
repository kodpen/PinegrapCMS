---
name: comments-widget-open-slots
description: Comments widget slots opened (CB2, #9716): Pagination as page links a designer builds, shared with Search results; CAPTCHA, star rating, publish options drawn true
module: widgets_comments
---

## What did CB2 open?
- Step CB2 (#9716); the census: [[canvas-closed-blocks-widgets]].
- Pagination: the `comment_pagination` section (Comments) and `pagination` (Search results) open as a tree: the page links list ([[widget-lists-paging]]).
- CAPTCHA (`comment_captcha`), Star rating input (`comment_rating_input`), Publish options (`comment_publish`): settings only, each drawn as a true sample on the canvas.
- Watcher list (`comment_watchers`): unchanged, it keeps its children (`_SD_SECTION_KEEPS_CHILDREN`).

## How does a designer open the pagination?
- Select the section's box; under "Section binding (system widget)" the button "Convert to design elements" (`_sdSectionToList()`) puts the Page links starter (`_sdPagingListStarter()`) in its place.
- Or the palette's "Lists in a record" -> Page links, now offered in the Comments widget too (`pg_sw_kind_lists('comments')`); not in a recent list.

## What does the site print for it?
- `pg_comments_widget_paging($page, $pages)`: tokens `__page_number`, `__page_count`, `__prev_page_url`, `__next_page_url` (escaped once), flags `has_prev_page`, `has_next_page`, `has_more_pages`, and the rows. Every link ends at `#software_comments`, as the section's: `pg_sw_list_paging()` and `pg_sw_list_paging_rows()` take an optional `$fragment`.
- `pg_comments_widget_draw()` reads `$data['paging']`: tokens fill the surround, flags join the thread flags there (thread flags win). The section holds `<!--pg-cm-pagination-->` until the tree is drawn; when the design drew a page list (`pg_sw_list_wanted('paging')`) the rows are published and the built-in links go.
- The paging flags are not thread flags (the 25 and their JS twin are unchanged); the canvas always draws a node on them.
- One page: no rows, no links, as the section printed nothing.
- `_pg_comments_tree_has_loop()` and the canvas (`_sdCommentsPlace()`, `_sdCommentsFlagOff()`, `_sdCommentsStateFor()`) skip a list with a source: the page links are never the comments' loop.

## Does a running site change?
- No: a saved tree with the section prints as before until its user converts it. The 57 design templates keep the section.

## How does the star rating input look?
- Owner (2026-11-15): as review sites draw it: five stars side by side, no number, filled up to the chosen one, the rest in outline; channel 25 messages 4700, 4728, 4747.
- `pg_comments_rating_input_html()`: a `role="radiogroup"` box (`data-pg-comment-stars`), btn-check radios named `rating` 1-5, ids `comment_rating_1..5`; each label holds a filled star (`data-pg-star-on`, `text-warning`) and an outline star (`data-pg-star-off`, `text-body-tertiary`), one of them `d-none`, and the words "N out of 5". No inline style, no stylesheet.
- `software.init_comment_forms()` (`frontend.src.js`, hand-patched `frontend.min.js`) swaps the stars on each change: click and arrow keys.

## What do the three settings-only slots draw?
- `_sdCommentsSectionSample(section, doc)`, in `buildNodeEl()` for an empty section box in a Comments widget, instead of the label box: what `pg_comments_rating_input_html()`, `pg_comments_publish_html()` and `get_captcha_fields()` print, inert, with the setting that shows it under it.
- No stored tree: the output depends on the request (a provider CAPTCHA, staff, the page's stars). Their faded state stays `_sdCommentsBindingOff()`'s.

## What stays closed?
- Nothing of the Comments widget's list. The CAPTCHA draws the sum question even where a provider widget (Security settings) replaces it on the site.

## How was it checked?
- `tests/comments_widget_tree_slots_test.php`: the fragment, the tree printing the classic links in order, the first and a single page, the classic output kept byte for byte without a list, the stars, the editor's twins. Sandbox: the editor pictures; the stars clicked and keyed in Chromium (both JS twins).
