---
name: chat-window-contrast
description: Panel chat window colours readable in both themes: own-message gradient, read ticks, active tab, emoji tool and send button
---

## Why not the primary colour for own messages?
- `--pg-primary` is the pastel `#64b5f6` in both themes; white on it is 2.21:1. Own messages in `chat.php` (`#pg-chat-root .pg-chat-bubble-me`) sit on a gradient fixed in both themes, `#1565c0` to `#6f42c1`: white text 5.75–6.5:1.
- Not `--pg-primary-fg-emphasis`: it turns light in the dark theme, under white text.

## What do the ticks on an own message use?
- Read tick `rgba(255,255,255,.85)` (was .65, 1.7–2.5:1) and the seen tick `#9ff3ff`: both 4.6:1 or more over the whole gradient.

## Which other chat controls changed?
- The active Conversations/People tab and the switched-on emoji button (`.pg-chat-tool-on`): `--pg-primary-fg-emphasis` (5.1/4.8:1 light, 8.4/6.6:1 dark; the primary was under 2:1 in light).
- The send and record-stop button keeps the `--pg-primary` fill with a `--pg-on-accent` icon (7.7:1); its disabled rule is unchanged.
- Left as they are: the launcher (white icon, 3.8:1), the unread badge, the emoji category tabs (colour glyphs) and the site chat `#pg-chat-site`.
- Pinned by `tests/chat_window_contrast_test.php`, which computes the bubble ratios from `chat.php`.
