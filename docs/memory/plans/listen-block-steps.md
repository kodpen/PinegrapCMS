---
name: listen-block-steps
description: Plan listen-block, steps LS1 to LS3: the script and its two doors, the block, close-out
---

## Which steps are there?
- LS1 (M) The script and its two doors. No schema. Done: #9914.
  - Existing files: `get_page_content.php` (one block beside the scroller's, for a page whose markup carries `data-pg-listen`), `assets/js/style_designer.src.js` (one row in `_SD_PAGE_SCRIPTS`).
  - New: `assets/js/pg_listen.js`, `includes/fn/listen.php` (the inject function and the labels), `tests/listen_script_test.php` (the pure function under Node); `tests/designer_page_scripts_test.php` covers the twin row.
  - Tests, red first: the pieces of the fixtures, the cut of `-x-mtfrom-`, a part's own language, nothing read outside `article` or `main`.
  - Features: L2 to L11.

- LS2 (S) after LS1: the block.
  - New: `assets/js/style_designer_blocks_listen.js`, copied from `tests/fixtures/designer/style_designer_blocks_sample.js`, its rules in the block CSS without `!important`, `tests/designer_blocks_listen_test.php`.
  - Pictures: the block on an article at 1440 and 390, light and dark, idle, speaking and paused; the canvas; a browser with no voice showing nothing. To the owner; not marked ready before his look.
  - Features: L1, L12, L13.

- LS3 (S) close-out: every L number checked against the code with its PR; `designer/designer-blocks-listen.md`; this plan's three files deleted and its line in `plans/website-features.md` removed.
