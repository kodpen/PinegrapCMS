---
name: member-widgets-open-slots
description: Sign-in and sign-up widgets' slots opened (CB7, #9895): password rules and the three links as elements, the sign-in buttons drawn true (settings only)
---

## What did CB7 open?
- Step CB7 (#9895); the census: [[canvas-closed-blocks-widgets]]. Widgets: login_form, registration, membership, change_password, set_password.
- Trees: the strong password rules (`password_rules`), the forgot password and sign-up links (login, `forgot_password_link`, `register_link`), the sign-in link (registration and membership, `login_link`).
- Settings only: the sign-in buttons (`google_signin`, `google_signup`), drawn as the site prints them.

## How does a designer open them?
- Select the section's box; under "Section binding (system widget)" the button "Convert to design elements" (`_sdMemberSectionToTree()`).
- Rules: the box keeps its binding and gets the rules as elements (`_sdPasswordRulesStarter()`: a heading line and a list of four rules, the words of `get_strong_password_requirements()`), with the block's classes `pg-password-rules small text-muted`. `password_rules` is in `_SD_SECTION_KEEPS_CHILDREN`.
- Links: the box loses its binding and holds a link on the token (`__forgot_password_url`, `__register_url`, `__login_url`) with the section's words and classes (`pg-member-link pg-…-link`). The new starters already draw their links this way.

## What does the site print?
- `pg_member_keep_designed_rules($tree, $rules_html)` before the section pass in the four renderers with rules: a `password_rules` box with children loses its binding while the site prints rules, so its children print as designed; with no rules (setting off, set password not on its form) the binding stays and the box goes. A box without children prints the server's block, as before.
- A link on its token is dropped when its address is empty and gets `rel="nofollow"` (`_pg_member_drop_empty_links()`). The box around it stays (an empty box when the link is dropped); the section dropped its box. The registration and membership sign-in link gains `rel="nofollow"`, as every action link the token path prints.

## What does the canvas draw?
- `_sdMemberSectionSample()` for an empty section box: the sign-in buttons from `siteWords.signin_buttons` (`pg_signin_canvas_sample()`: one button per provider turned on, Google's alone when none, faded then), with "Turned on now"; the rules (`__password_rules` preview); the link.
- `pg_signin_button_label()` gives the button words to the site and the sample alike.

## How was it checked?
- `tests/member_widget_open_slots_test.php`: designed rules kept, gone when off, an empty box unchanged, button words, renderer and editor wiring. Sandbox pictures before and after, 1440 and 390, light and dark.
