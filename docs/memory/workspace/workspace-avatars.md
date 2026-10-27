---
name: workspace-avatars
description: How a person's face is drawn in the panel on Bootstrap 6 Avatar (step B9j): initials and tone from the server, the eight theme tones, presence dot, stacks, no empty src, what keeps the data: picture
module: contacts
---

## Where does a face come from?
- The server: `ws_person_brief()` (`includes/workspace/people.php`) gives `avatar` (a picture address, or the data: picture of letters), `avatar_kind` (`photo` | `contact` | `user`), `initials` (`pg_initials()`, Turkish upper-casing) and `tone` (0-7, `-1` neutral). Guests, applications, approval guests and MCP servers carry the same fields; a source that copies a person's fields uses `ws_avatar_fields($person)`, never field by field.
- `tone` is `pg_avatar_tone($seed)` (`includes/fn/contacts.php`): `crc32` of the seed modulo 8, the user id for people, `guest<N>`, `app<N>`, `approval-guest<N>`, `mcp<N>` for the others. Stable per person; a login without a name gets `-1`.

## How is it drawn?
- JavaScript: `avatar(person, size, opts)` in `assets/js/workspace.js` returns `<span class="avatar ws-avatar">`: an `.avatar-img` for `avatar_kind` `photo`, else the letters with `theme-<name>` from `AVATAR_TONES`. A picture that fails to load gives way to the letters. No `<img src="">` any more (it was drawn for people without a picture).
- PHP: `ws_avatar_html($person, $class)` prints the same markup (task cards, `tasks.php`); `pg_avatar_theme($tone)` names the class. The order (primary, info, warning, accent, danger, secondary, success, inverse) is kept in both; `tests/avatar_initials_tone_test.php` fails when they differ.
- A login without a name: `.ws-avatar-square` (radius 28%) and the neutral avatar, so the missing name shows.
- Size: an inline `--bs-avatar-size` (the `size` argument) or a context rule `.X .avatar { --bs-avatar-size: … }` in the Workspace block of `backend.src.css`. Never `width`/`height` on the `img`, and never replace the wrapper's `className` (add with `classList`).
- Presence: `opts.presence` adds `.avatar-status status-online|away|offline` with `role="img"` and a translated label (members drawers). Otherwise the face is `aria-hidden`: the name stands beside it.
- Stacks: `avatarStack(people, max, class)` builds `.avatar-stack.ws-avatars`. `.avatar-stack` is `row-reverse`, so the helper appends people backwards and the first reads left; Bootstrap does the overlap and border, `.ws-avatars-sm` sets a 1px border.

## What keeps the data: picture?
- `pg_initials_avatar_url()` stays for e-mail, PDF, print, the external API's `avatar` field and anything outside the panel's CSS; its colour is the same tone index as the panel's theme.

## What is still to do (B9j slices)?
- Slice 1 (this file's PR): server fields, Workspace screens. Still on `<img>`: the chat (`chat.php`, `chat_backend.src.js`), the guest page (`workspace_guest.js`), the @ picker rows (`.ws-picker-item img`), other panel sites (dashboard widgets, contacts, orders); see `plans/bootstrap-6-panel-steps.md`.
