---
name: comments-widget-open-slots
description: Comments widget slots opened (CB2, #9716): Pagination as page links a designer builds, shared with Search results; CAPTCHA, star rating, publish options drawn true
module: widgets_comments
---

## What did CB2 open?
- Step CB2 of `plans/designer-closed-blocks-steps.md` (#9716). The census: [[canvas-closed-blocks-widgets]].
- Pagination: the `comment_pagination` section (Comments) and `pagination` (Search results) open as a tree: the page links list ([[widget-lists-paging]]).
- CAPTCHA (`comment_captcha`), Star rating input (`comment_rating_input`), Publish options (`comment_publish`): settings only, each drawn as a true sample on the canvas.
- Watcher list (`comment_watchers`): unchanged, it keeps its children (`_SD_SECTION_KEEPS_CHILDREN`).

## How does a designer open the pagination?
- Select the section's box; under "Section binding (system widget)" the button "Convert to design elements" (`_sdSectionOpenRow()`, `data-sd-section-open`) replaces the box with the Page links starter (`_sdSectionToList()` -> `_sdPagingListStarter()`): previous, one link per page, next. The box's own classes and show condition move to the new `nav`.
- Or the palette's "Lists in a record" -> Page links, now offered in the Comments widget too (`SW_LIST_SOURCES['paging.pages'].kinds`, `pg_sw_kind_lists('comments')`). Not in a list of recent comments.

## What does the site print for it?
- `pg_comments_widget_paging($page, $pages)`: tokens `__page_number`, `__page_count`, `__prev_page_url`, `__next_page_url` (escaped once), flags `has_prev_page`, `has_next_page`, `has_more_pages`, and the rows. Every link ends at `#software_comments`, as the section's: `pg_sw_list_paging()` and `pg_sw_list_paging_rows()` take an optional `$fragment`.
- `pg_comments_widget_draw()` reads `$data['paging']`: tokens fill the surround, flags join the thread flags there (thread flags win). The section holds `<!--pg-cm-pagination-->` until the tree is drawn; when the design drew a page list (`pg_sw_list_wanted('paging')`) the rows are published and the built-in links go, in the section and after the comments.
- The paging flags are not thread flags: the 25 flags and their JS twin are unchanged. On the canvas a node on them is always drawn.
- One page: no rows, no links, as the section printed nothing.
- `_pg_comments_tree_has_loop()` and the canvas (`_sdCommentsPlace()`, `_sdCommentsFlagOff()`, `_sdCommentsStateFor()`) skip a list with a source: the page links are never the comments' loop.

## Does a running site change?
- No. A saved tree with the section prints the built-in links as before; only its user's "Convert to design elements" changes it. The 57 design templates keep the section.

## What do the three settings-only slots draw?
- `_sdCommentsSectionSample(section, doc)`, in `buildNodeEl()` for an empty section box in a Comments widget, in place of the label box: what the site prints (`pg_comments_rating_input_html()`: five star buttons, 5 picked; `pg_comments_publish_html()`: Publish with Now / At a Scheduled Time / Maybe Later; `get_captcha_fields()`: the sum question and answer box), inert, with the setting that shows it under it.
- No stored tree: the output depends on the request (a provider CAPTCHA, staff, the page's stars). Their faded state stays `_sdCommentsBindingOff()`'s.

## What stays closed?
- Nothing of the Comments widget's list. The CAPTCHA draws the sum question even where a provider widget (Security settings) replaces it on the site.

## How was it checked?
- `tests/comments_widget_tree_slots_test.php`: the fragment, the tree printing the classic links in order, the first and a single page, the classic output kept byte for byte without a list, the editor's twins. No sandbox or browser run in the PR.
