---
name: panel-avatar-sites
description: Where the panel draws people as the Bootstrap 6 Avatar (step B9j slices) and where the picture of letters (pg_initials_avatar_url()) stays
module: contacts
---

## Which sites are converted?
- Header button and account menu (#2216); the Workspace screens, task card people, Workspace settings table (#2242); chat (`avatarNode()` in `chat_backend.src.js` and its hand-kept `.min.js` twin, `avatar_kind` from `pg_chat_avatar_kind()`), dashboard widgets 4 (presence dot), 28 and 30 (the Avatar fills the 34px row tile), the contacts list and the order screen (`pg_avatar_markup_for()`, the twin of `pg_avatar_for()`), the guest page (`workspace_guest.js`, own `AVATAR_THEMES`), the delegate tag (`ws_assistant_face()`).
- The designer toolbar keeps its own `.sd-presence-avatar` (editor chrome colours) but draws the letters (`initials` from `pg_collab_user_brief()`), not the picture of letters. `edit_contact.php` still shows the picture of letters.

## What keeps the picture of letters?
- `pg_initials_avatar_url()` stays for e-mail, PDF and print (`print_order.php`), and for site output (Bootstrap 5.3.8, `widgets_account.php`), which has no `.avatar`. The account menu's quick-form preview `img[data-pg-contact-preview]` keeps it too: the chosen file replaces its `src`.
