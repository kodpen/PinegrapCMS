---
name: panel-avatar
description: The panel Avatar (pg_avatar_markup()): letters on eight theme tones, grey square for a nameless login, presence dot, no empty src; initials and tone on person briefs
module: contacts
---

## How does the panel draw a person?
- `pg_avatar_markup($person, $size, $class)` in `includes/fn/contacts.php` prints `<span class="avatar …">`: the photo as `<img class="avatar-img">` when there is one, else the letters as text on `pg_avatar_theme_class($tone)`. Sizes `xs|sm|lg|xl` (v6 classes); a size of its own is `--bs-avatar-size` on the extra class (`#header .pg-avatar`, `.pg-um-avatar`).
- A login without a name in the address book (`avatar_kind` `user`) is `avatar-subtle theme-secondary pg-avatar-user`, a 25% rounded square, so the missing name shows, as the picture of letters drew it.
- No `<img>` is printed without an address; an `avatar` that is a picture of letters (`data:image/svg+xml`) or a kind `contact`/`user` is drawn as letters. A drawn icon without a kind (`ws-ai.svg`, `ws-assistant.svg`) stays a picture.
- `presence` `online|away|offline` adds `<span class="avatar-status status-…">`; anything else adds no dot. A `name` becomes `role="img" aria-label`, else `aria-hidden`.

## Where do the letters and tone come from?
- `pg_avatar_tone($initials, $seed)`: `abs(crc32(seed ?: initials)) % 8`, the place `pg_initials_avatar_url()` always picked its hex from, so a person has one colour in the panel, an e-mail and a print. `pg_avatar_letters()` returns `initials` + `tone`.
- Every person brief carries `initials` and `tone` beside `avatar`/`avatar_kind`: `ws_person_brief()` (`ws_avatar_letters()`, seed the user id), the former member (`?`, 0), guests (`guest<id>`), applications (`app<id>`, now also `avatar_kind` `contact`), approval guests (`approval-guest<id>`), MCP identities (`mcp<id>`), and the chat's person and peer rows (`chat.php`, seed the user id).
- The external API presents people as `{user_id, name}` and does not carry these fields.

## Which tones?
- In place order: `theme-primary`, `theme-info`, `theme-warning`, `theme-accent`, `theme-danger`, `theme-secondary`, `theme-success`, `theme-inverse`, near the old hex list (blue, teal, orange, violet, pink, sky, green, amber).

## What keeps the picture of letters?
- `pg_initials_avatar_url()` stays for e-mail, PDF and print (`print_order.php`), and for site output (Bootstrap 5.3.8, `widgets_account.php`), which has no `.avatar`. The account menu's quick-form preview `img[data-pg-contact-preview]` keeps it too: the chosen file replaces its `src`.

## Which sites are converted?
- The header account button and the account menu's identity row (`pg_account_menu()`, first slice of step B9j). The rest is listed under B9j in `plans/bootstrap-6-panel-steps.md`.
