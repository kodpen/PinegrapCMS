---
name: panel-shell-foot
description: Panel foot at the window's bottom, none on the Workspace channel view; top bar description ends 16px before the search field; no Ctrl+K there on Workspace screens
---

## Where does the foot (`#footer`, `output_footer()`) stand?
- At the window's bottom when a screen is short, after the content when it scrolls. `body:not(.toolbar) .software-content` is a flex column with `min-height: calc(100vh - var(--pg-navbar-h))` (100dvh where supported), and `> #footer` has `margin-top: auto`. No fixed foot over the content. Built in #11075 (plan workspace-interface, step WI4).
- Before, the foot stood right under the content: in the middle of a short screen, and under the channel's writing box, where it took about 85px of the conversation.
- The visual editor (`body.toolbar`) prints no foot and is left out of the rule.
- A flex item is its own formatting context, so a margin of the first or last element inside a direct child of `.software-content` no longer runs out of it. The sweep of every panel screen at 1440px before and after (#11075) found nothing but the foot moved.

## Which screen prints no foot?
- The channel view of `workspace.php`: `body.workspace_channels .software-content:not(:has(.ws-home)) > #footer { display: none }`, and `.ws-app:not(:has(.ws-home))` is `calc(100vh - var(--pg-navbar-h))` high, so the writing box stands at the window's bottom. Today (`.ws-home`, drawn by `showHome()` in the same root) keeps the foot and the old `calc(100vh - 8.5rem)`.
- Hidden by CSS, not left out by `output_footer()`: the script switches between Today and a channel without loading the page, so the server cannot know the view. `output_footer()` prints the same on every screen (the chat launcher and its scripts stay); a private-label site prints no product lines anyway.

## Where does the top bar's description end?
- From 1400px, where the search field stands in the bar's middle, `.pg-navhead` is at most `min(26rem, calc(50% - var(--pg-searchbar-w) / 2 - 15.375rem))`: 234px for the menu button, logo group and separator in front of it, 16px of gap, less its own 4px end padding. The description ends 16px before the field with an ellipsis; its full text is in the heading's popover.
- The old `50vw - … - 12rem` let the description run 42px under the field at 1400px.
- A private-label logo is narrower, so the gap is only wider there.

## Why no Ctrl+K on a Workspace screen's top bar?
- `body.workspace #header .pg-searchbar-kbd { display: none }`: on a Workspace screen Ctrl+K opens the Workspace's own search, which the list's field in the main menu names.

## What keeps it?
- `tests/workspace_screen_frame_test.php`: the column and the foot's auto margin, no fixed foot, the channel view's two rules, `output_footer()` deciding its lines by private label alone, the hidden key.

Related: docs/memory/workspace/workspace-screen-frame.md, docs/memory/ui/panel-top-bar-phone.md
