---
name: workspace-nested-controls
description: Why the channel list's pin and the search palette's account link are aria-hidden mouse shortcuts inside their link/option, and the keyboard routes to the same actions (Shift+F10 menu, Shift+Enter)
---

## Why is the channel pin aria-hidden with no button role?
- `channelLink()` in `workspace_side.js` builds the pin, "⋯" and Join with `rowShortcut()` inside the `<a class="ws-channel-link">`; since #11079 they show on pointing only, a pinned row's pin included. A `role="button"` there was a control nested in a link that the keyboard could not reach, and on a pinned channel (pin always visible) its title joined the link's name ("general Unpin, link").
- It is now `aria-hidden="true"` and stays a mouse shortcut. The keyboard pins from the channel's context menu: Shift+F10 or the menu key on the focused link opens `channelActions()` with Pin/Unpin (workspace.js).
- "⋯" opens the same `channelActions()` menu (`hooks.channelMenu`); Join is reached by opening the channel, whose screen has its button.

## Why is the palette's 'Open the account' link out of the Tab order?
- `addRow()` in `workspace_palette.js` puts the link inside a `role="option"` of a listbox driven by `aria-activedescendant`. Its text joined the option name, and Tab from the search box walked onto a row of links all named 'Open the account'.
- The link has `tabindex=-1` and `aria-hidden="true"`; `onKey()` opens the account on Shift+Enter when the active line `hasAccount()` (a colleague, not me, with a URL, chat present). The foot hint (`pal_hint`, includes/workspace/palette.php) names Shift+Enter.
