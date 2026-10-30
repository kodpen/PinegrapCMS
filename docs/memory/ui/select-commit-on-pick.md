---
name: select-commit-on-pick
description: Selects that save or ask the moment they change (Translations engine, "Write with AI" tone) wait for a keyboard pick; the AI panel gives focus back after a request
---

## Why can an arrow key not save a select?
- In Chrome/Edge on Windows and Linux and in Firefox, an arrow key on a closed select changes its value and fires `change` at once, on every step. A select that saves on change then saved every value on the way.
- Translations screen engine select (`#pg_tr_engine`, `assets/js/translations.js`): each step posted `action=engine`, which writes `site_languages.engine`, logs an activity and clears `fallback_engine` when it equals the new engine; then the screen reloaded. Passing over the fallback engine wiped it.
- "Write with AI" tone select (`assets/js/pg_ai_write.js`): each step asked again and took a draft from the day's quota.

## How do they commit now?
- A navigation keydown (arrows, Home, End, PageUp, PageDown) sets a flag; a `change` after it is held, and the pick is committed on Enter or `blur`. A `pointerdown` clears the flag, so a pointer pick commits at once as before.
- A commit does nothing when the value equals the saved one: `config.engine` for the engine, `askedTone` (the tone of the last request, set in `begin()`) for the tone.
- `tests/select_commit_test.php` pins the listeners.

## Where does focus go while the AI panel asks?
- `setBusy(panel, true)` disables the tone and "Try again", which drops focus to the page. It remembers the control inside the panel that had focus and `setBusy(panel, false)` focuses it again, unless focus moved elsewhere meanwhile or the panel was closed.

## Where does focus go after a switch that posts on change?
- Back to the switch. Cloudflare Tools redirects with `?focus=<switch id>` and draws that switch `autofocus`. Site Log's Show Error Logs switch (`view_log.php`, administrators only) does the same: a POST with neither Delete button redirects with `?focus=error_log_toggle` and the switch prints `autofocus`. Before, focus fell to the page top after every toggle. Pinned by `tests/developer_lock_hint_test.php`.

## Related
- Cloudflare Tools switches submit on change too; Ctrl+S is kept off them with `disable_shortcut` (`panel-save-shortcut.md`).
- Panel-wide filter selects (`pg_filter_select()`) are not covered here.
