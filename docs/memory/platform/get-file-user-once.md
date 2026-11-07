---
name: get-file-user-once
description: get_file.php's initialize_user() returns when the visitor is already read; a private channel's file reached it twice and the PHP warnings broke its picture
---

## Why does get_file.php's initialize_user() return when USER_LOGGED_IN is set?
- One request can reach it twice. The workspace block reads the visitor for any file in a channel's folder (a public channel's file opens to the whole team), and the folder check calls it again when the folder is not public: a private, guest or thread channel's file, or a public channel's file opened by someone outside the team. The other callers ask `defined('USER_LOGGED_IN')` themselves; the folder check does not, so the function guards itself.
- Symptom fixed: defining the USER_* constants again raised eight PHP warnings ("an error in PHP 9" from 8.5). Where `display_errors` is on (the IIS test site), they went out ahead of the file's bytes, so every picture of a private channel showed broken: a canvas sent from the image editor, an uploaded image too. With errors hidden they only filled the error log; PHP 9 would make them fatal.
- A public channel's file skips the folder check, which is why a try in a public channel did not show it.

## How is it checked?
- `tests/get_file_user_once_test.php`: the guard comes before the first query or definition, and the folder check still calls the function after the workspace block.
- By hand: with `display_errors` on, the picture's bytes start with `89 50 4E 47`, not `<br />`.
