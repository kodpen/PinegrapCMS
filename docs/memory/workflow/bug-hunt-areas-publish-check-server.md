---
name: bug-hunt-areas-publish-check-server
description: Bug hunt of the designer's Publish check, server side (2026-11-26): three faults fixed in seo_check and the weight measure, what was read clean
module: publish_weight
---

## What was the area?
- The server code the Publish check runs: `designer` / `seo_check` in `includes/panel/designer.php`, the draft render and weight in `includes/fn/seo_draft.php`, `includes/fn/publish_weight.php`, and the save's "Publish anyway" reason (`pg_designer_publish_check_reason()`). Named by the architect. Not the drawing, the client panel, Clean up, or page versions (`bug-hunt-areas-page-versions-publish.md`).

## What did it fix?
- A picture address with an encoded null byte in the draft (`/a%00.jpg`) threw a ValueError in `realpath()` (PHP 8): `seo_check` answered an error page and every group of the page said "Not checked". `pg_publish_weight_local_files()` leaves such an address out, as a missing file. Test: `tests/publish_weight_test.php`.
- Several pictures drawn from one editor node (a gallery, a carousel, a widget's list) all got the id `weight:img_no_size:<node>:0`: "Ignore on this page" on one hid all of them and the count fell by as many. `pg_publish_weight_findings()` numbers the pictures of each node (0, 1, 2 …); the first keeps its old id, so a kept Ignore still holds. A picture with no node keeps the page-wide index.
- `seo_check` read a page's stored SEO findings, link score and speed by the id the request named, also for a content-level operator to whom the page is `hidden` (`pg_designer_page_access()`), which `page_load` refuses. It now answers "Permission denied." for such a page.

## What was read clean?
- CSRF: `pg_panel_designer()` runs `validate_token()` before any sub-action; `seo_check` is in the content-level allow-list on purpose.
- The draft render: access must be `edit`, the tree goes through the save's gate, the session is lent empty and restored in `finally`, the override is cleared.
- Weight: the budget and grades, the unused-rule reader (loops always move on, at-rules without style rules give none), the file sizes stay inside their root (`realpath()` checked against it), other hosts and schemes are skipped.
- The reason: tags stripped, one line, 500 characters; `lang()` fills its vars once, so a `{var:n}` in the reason is printed as typed; logged only when the page ends on the site.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/designer/designer-publish-weight.md, docs/memory/designer/designer-publish-overview.md
