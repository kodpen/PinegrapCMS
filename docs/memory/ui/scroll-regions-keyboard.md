---
name: scroll-regions-keyboard
description: Text-only boxes that scroll on their own are named keyboard regions: Audit Trail JSON values, Workspace MCP answer, API documentation tables and Try it answer, Write with AI draft
---

## Why does a scrolling text box need a Tab stop?
- A box capped by `max-height`/`min-width` with `overflow: auto` that holds only text has nothing Tab can reach: Safari never focuses a scroller by itself, so its hidden lines cannot be scrolled from the keyboard, and Chrome and Firefox focus it without a name.
- The fix is `tabindex="0"`, `role="region"` and a translated `aria-label`. The panel's global `[tabindex]:focus-visible` ring (`backend.src.css`, WCAG block) shows the focus; both API pages load that stylesheet.

## Which boxes carry it?
- Audit Trail changes table: a JSON value's `<pre>` (16rem) in `pg_audit_list_value_html($value, $name)`, named `{var:1}, before` / `{var:1}, after` with the field. Plain values do not scroll and get nothing.
- Workspace MCP tool runner (`workspace_mcp.php`): the Answer `<pre>` (30rem), named `Answer`.
- API documentation (`includes/api/console_view.php`, used by `api_docs.php` and the public console): the Try it `<pre>` (380px), named `Response` (`T.response`).
- Its `.doc-params` holders (tables with `min-width: 520px`) scroll only below about 560px, so `markScrollers()` in `pgApiConsole.init()` sets the three attributes while `scrollWidth > clientWidth` and removes them otherwise; a 200 ms debounced window resize rechecks. The name is the endpoint's `.doc-path` text and the table heading (`/orders – Query`).
- Write with AI draft (`pg_ai_write.js`, `showDraft()`, 18rem): named by the panel's title label (`title` / `title_browser`), so no new label key.

## What is left?
- The guided tour body (`pg_tour.js`) and the panel chat conversation (`chat_backend.src.js`, role log) are not done yet.

## What was checked in a browser?
- `markScrollers()` in headless Chromium at 400px (attributes set) and 1000px after resize (removed). The other boxes are pinned by `tests/scroll_regions_keyboard_test.php`.

Related: docs/memory/ui/dashboard-scroll-regions.md, docs/memory/ui/panel-accessible-names.md, docs/memory/ui/panel-focus-ring.md
