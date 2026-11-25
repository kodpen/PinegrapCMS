---
name: calls-leave-page
description: Leaving the Workspace during a call (CN3, #11386): links that leave the page open in a new tab, a reload or a close asks first
---

## What happens to a link clicked during a call?
- While the person is in a call, a link that would take the page elsewhere (an order, a customer, a site page, the panel's menu, an outside address) opens in a new tab, so the call's page and its connections stay. The first time in a call a toast says "Opened in a new tab, so the call goes on.".
- Architect (2026-11-24): a new tab, not a call that follows the person onto another panel page, because the connection lives in its page and cannot move; the owner had left both ways open (`workflow/decisions-made-workspace-50.md`).

## Which links count as leaving?
- `leavesPage()` (pure, `assets/js/workspace_calls.js`): another path or query of the site, or another site, in this tab (`_self`, `_top`, `_parent` or no target). Stay: `#…`, the same address with a hash, `javascript:`, `mailto:`, `tel:`, `sms:`, `data:`, `blob:`, a `download` link, a link already aimed at `_blank` or a named window.
- The listener is on the window (bubble), after the Workspace's own handlers: a link they handle in the page (`defaultPrevented`, e.g. opening a channel) is left alone. A click with Ctrl, Cmd, Shift or Alt or another mouse button keeps the browser's own way.

## What does a reload or a close do?
- A `beforeunload` listener asks first; the browser writes the question. It is added when the call begins and removed in `finish()`, so outside a call the page keeps its back-forward cache.
- If the person goes anyway, `pagehide` still sends the leave (`calls-endurance.md`).

## How was it checked?
- Unit tests: `tests/workspace_calls_leave_page_test.php` (`leavesPage()` under node, the wiring, the text). Not run in a browser.
