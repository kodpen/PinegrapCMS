---
name: style-usage-counts
description: How the styles list counts the folders and pages that use each style, inherited desktop and mobile styles included
module: style_usage
---

## Where are the counts made?
- `view_styles.php` reads the folder and page rows once and passes them to `pg_style_usage()` (`includes/fn/style_usage.php`, pure, loaded by require_once there only). Tests: `tests/style_usage_test.php`.

## What does a folder or page count for?
- The styles it works out to: its own desktop and mobile style, else its parent folder's, desktop and mobile apart, as `get_style()` picks them.
- A mobile style that works out to the desktop style is counted once. Style 0 (none) is not listed; folders reachable only through a loop in the rows are not walked.

## What did it fix?
- The old recursive count compared the stored ids: a page with neither style of its own never counted its folder's mobile style, and a page whose own desktop style equalled the inherited mobile one was counted twice (#6180).
