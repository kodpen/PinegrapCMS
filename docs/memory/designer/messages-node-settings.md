---
name: messages-node-settings
description: The Messages node's settings (Alert look, Dismiss button) and its sample error on the designer canvas; PHP and JS twins, placeholder form, defaults that keep a published page's output (CB5, #9723)
module: designer_messages
---

## What can a user change on a Messages node?
- Since #9723 (plan step CB5): Alert look (Standard, Line on the left, Filled, Compact) and Dismiss button, in the node's settings panel, beside its CSS class.
- Line on the left adds `border-0 border-start border-4 border-<colour>`; Filled `text-bg-<colour>` (and `btn-close-white` on red and green); Compact `py-2 small`. The colour follows the kind: error danger, notice success, warning warning.
- Dismiss button shows the close button every box always carried hidden (`style="display:none;"`).

## Does a published page change?
- No. With neither setting the boxes are byte for byte what `liveform::output_errors()` prints (`tests/designer_messages_settings_test.php`). A stored node without the props reads as the defaults.

## Where is the markup written?
- `pg_messages_alert_html($kind, $lines_html, $settings, $extra_class)` in `includes/fn/designer_messages.php`, for both paths: a widget's node (`_render_tree_node()`, case `messages`, settings from `pg_messages_settings($props)`) and the page-level placeholder (`get_page_content.php`).
- The messages depend on the request, so the saved body keeps a placeholder: `<!--pg-messages-placeholder-->` with no setting, `<!--pg-messages-placeholder:<look>:<0|1>-->` with one (`pg_messages_marker_settings()`). The first placeholder of a page is filled, the others removed, as before.

## What does the canvas draw?
- A true error box in the node's look: the heading "An error occurred:" and a sample line, the close button when it is on (no `data-bs-dismiss` on the canvas, so a click never removes the node). It replaced the dashed "PHP Messages" box.
- JS twin: the `messages` entry of `CONTENT` in `style_designer.src.js` (`looks`, `look()`, `alertClass()`); its panel rows sit in `propsContent()`. A new look goes into both sides and the test.

## What stayed closed?
- The messages themselves (per request) and the heading text; no tree is stored for the node.

Related: docs/memory/designer/system-widget-render-rules.md, docs/memory/designer/canvas-closed-blocks-content.md
