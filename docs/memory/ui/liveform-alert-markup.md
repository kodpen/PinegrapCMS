---
name: liveform-alert-markup
description: The alert around liveform's errors, warnings and notices: Bootstrap 6 alert component in the panel (PG_PANEL_OUTPUT), Bootstrap 5 markup on sites, the v5 fallback rule
---

## What did the panel show before?
- `output_errors()`, `get_warnings()` and `output_notices()` printed v5 markup everywhere: `alert alert-success alert-dismissible show`, the close button first. v6 has no `alert-dismissible`, and the panel's shell rule makes `.alert` a block, so the cross stood alone at the top left above "✔ Notice:" in a full-width box (Site Settings after a save, every screen with a notice).

## How does liveform choose the markup?
- `output_header()` and `output_header_secure()` define `PG_PANEL_OUTPUT`; `liveform::panel_output()` reads it. Panel screens build their output left to right (`pg_page_shell(...) . $liveform->output_notices()`), so the frame comes first.
- Panel (`liveform::alert_box(..., true)`): `<div class="software_<kind> alert theme-<tone> d-flex align-items-start gap-3" role="alert">`, the heading and lines in `<div class="flex-grow-1 min-w-0">`, then a visible `btn-close ms-auto flex-shrink-0` with `data-bs-dismiss="alert"`. Heading icons are Bootstrap Icons with `me-3` (0.5rem in v6); the warning's PNG and inline ink are gone, theme-warning colours it.
- Without the constant (site pages, site AJAX endpoints, a panel screen that prints its messages before its frame): the old v5 markup, close button hidden until `backend.src.js`/site script shows it.

## What keeps a v5 alert readable in the panel?
- backend.src.css reads `.alert[class~="alert-dismissible"]` like `.pg-alert-dismissible` (3rem right padding, the close button absolute at the top right) and maps `alert-<tone>` to v6 theme tokens.

## What is not covered?
- `get_page_content.php`'s messages placeholder builds its own v5 alerts: site output, untouched.
- Not checked in a browser; pinned by `tests/liveform_alert_markup_test.php`.

Related: docs/memory/ui/bootstrap-6-runtime-markup.md, docs/memory/ui/settings-card-chrome.md, docs/memory/ui/liveform-notices-trap.md
