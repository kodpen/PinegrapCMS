---
name: live-filter-status
description: Live filters that say what they found (API applications, Workspace MCP tools, marketplace picker and category search): hidden status region, empty line, Enter in the MCP tool filter
---

## How does a live filter say what it found?
- A visually hidden `role="status"` element next to the filter carries the translated texts in `data-found` (`lang('{var:1} found')` printed with `{1}` as the hole) and `data-none` (`Nothing found.`). About 500 ms after the typing stops the script writes the count of visible rows, or the none text; an empty filter clears it. Same idea as `announceSearchCount()` in the file manager (`explorer-search-state.md`).
- A visible empty line (`Nothing found.`) shows under the list when the filter hides every row. Before, a word that matched nothing left an empty card and a screen reader heard nothing.
- `api_settings.php`: `#api_filter_status`, `#api_filter_empty`. The `.api-row` rows are a grid in the page's own style, so they keep the inline `style.display` (an unlayered `display: grid` beats Bootstrap 6's `d-none`).
- `includes/workspace/mcp.php` tools form: `[data-ws-mcp-filter-status]`, `[data-ws-mcp-empty]`, one per server form.

## What does Enter do in the MCP tool filter?
- Nothing: a `keydown` listener calls `preventDefault()` on Enter (not while `isComposing`) in `[data-ws-mcp-filter]`. Before, Enter pressed the form's first submit button: Save for an administrator (every switch saved, the filter word lost on reload); for a designer or manager the form has no submit button but the filter still submitted it implicitly, and `ws_mcp_settings_post()` answered "Only an administrator can add MCP servers."

## And the marketplace pickers?
- `assets/js/marketplace_products.js`: the product picker's caption (Loading, the result title, Nothing found, the error) is `role="status"` `aria-live="polite"`. The category search writes Loading, Nothing found and the error into its own status line above the results instead of into the results div; opening the dialog again clears it. Only the existing `label()` keys are used.

Test: `tests/live_filter_status_test.php`.
