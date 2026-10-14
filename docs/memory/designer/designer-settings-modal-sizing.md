---
name: designer-settings-modal-sizing
description: Designer settings modal sizes (.sd-settings-nav 230px, .sd-settings-body 640px) live in style_designer.css, not inline; rail becomes a strip under 992px.
---

## Where do modal and panel dimensions live?
- The settings modal's rail (`.sd-settings-nav`, 230px) and form panel (`.sd-settings-body`, 640px) were measured with inline `style`; inline style loses to no media query, so the narrow-screen rule became a pile of `!important`. Size, color and font size are in `style_designer.css`.
- **Write no inline dimensions on panel/modal skeletons**: width, `max-width`, `flex-direction`, padding. Bootstrap helpers like `flex-column`, `p-4`, `mb-3` are the same trap: they produce `!important` and must be undone in media queries. Content boxes (form row, badge) are free.

## What happens below 992px?
- **The rail turns into a horizontal strip** and group titles (`.sd-settings-nav-title`) are hidden. Titles make sense beside a vertical list; each tab panel's opening scope sentence already gives the same information, so nothing is lost.
- When adding a new tab keep the pill text short: pills sit side by side in the strip.
