---
name: chat-window-contrast
description: Panel chat window colours readable in both themes (own-message gradient, read ticks, active tab, emoji tool, send button) and text and box sizes in rem
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
- Windows High Contrast (forced colours): an `@media (forced-colors: active)` block at the end of the `#pg-chat-root` style gives the panel `border: 1px solid CanvasText` (it was `border: 0` with only a shadow) and the open tab `forced-color-adjust: none` on `Highlight`/`HighlightText`; the focused row's `outline: 0` became `outline: 2px solid transparent`, which forced colours paint. Pinned by `tests/forced_colors_widgets_test.php`.
- Pinned by `tests/chat_window_contrast_test.php`, which computes the bubble ratios from `chat.php`.

## Why are the window's text and box sizes in rem?
- So the window grows with the browser's default text size. Every `font-size` in the `#pg-chat-root` block was px (9.5–13 px) in a fixed 372×544 px window, while Bootstrap's rem text inside it (the header presence line, buttons) doubled at a 32 px default and outgrew the 13 px name above it.
- Now text is rem at the 16 px base, the chip's line height is unitless, and the window is `23.25rem` × `34rem` under the unchanged `calc(100vw - 32px)` / `calc(100vh - 128px)` caps. The composer (min/max height), emoji grid, badge, file tag and avatars are rem too. The 58 px launcher, the phone layout and the site chat `#pg-chat-site` stay px.
- `chat_backend.src.js` sets the header title, @username and AI badge in rem, and the composer grows up to the textarea's computed `max-height` (96 if it cannot be read) instead of a literal 96.
- Pinned by `tests/chat_window_text_size_test.php`.
