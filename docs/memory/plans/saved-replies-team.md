---
name: saved-replies-team
description: Plan (owner 2026-11-09): team saved replies beside the personal ones, a right to manage them, and the reply list in the e-mail reply box; steps SR1-SR2
---

## What is it for?
- Saved replies are personal, so each person writes the same answers to customers again. The table was made with a team slice in mind (`scope`, #3075, `workspace/workspace-saved-replies.md`); only the right and the reading were left.
- Decision: `workflow/decisions-made-workspace-10.md`; schema: `workflow/decisions-made-schema-14.md`.

## How does it behave?
- Every Workspace member sees the team's replies in the `;` list under their own, marked "Team", and inserts one as a personal one is inserted, `{customer}` and `{order}` filled the same way (`ws_saved_reply_fill()`). A personal reply with the same shortcut comes first.
- Managing them (add, change, delete) takes the right "Manage team replies": roles 0-2 hold it, a role-3 user by the column. The drawer (`PGWsReplies.drawer()`) gets a Team part, read-only without the right.
- Guests and customers never load any saved reply, as today.

## What is decided about the store?
- A team reply is a `ws_saved_replies` row with `scope` `team` and `user_id` 0. The unique key (`user_id`, `scope`, `shortcut`) then keeps a team shortcut unique for the whole team, and a deleted user takes no team reply along. No table change (#3075).
- The right is `user.manage_workspace_replies`, read in `ws_rights()` behind a column probe of its own (`core/permission-columns.md`). It does not open the panel on its own (`manage_workspace` stays the gate), so the role-3 ladder files do not learn it. It is drawn with the other Workspace rights on the three user screens.

## Which steps are there?
- SR1 (M, schema) Team replies. The column. `saved_replies.php` reads and writes the `team` scope beside `WS_SAVED_REPLY_PERSONAL` (`ws_saved_replies_for_box()`, `ws_saved_reply_save_check()` with a limit of its own). The panel actions and the routes of `api_saved_replies.php` take `scope` and refuse a team write without the right. The drawer's Team part. Tests: the right, the order on a shared shortcut, a team shortcut already taken. The step rerun twice on a dev DB. Memory: `workspace/workspace-saved-replies.md`. Claim as `[plan:saved-replies-team] SR1 — team replies`.

- SR2 (S, no schema) The list in the e-mail reply box. The reply drawer of `workspace_mail.js` (`compose()`, a plain textarea) opens the same `;` list and inserts the reply as plain text, filled as in the channel (`ws_saved_reply_values()`). Apart from SR1; either may land first.

## Done when?
- Both steps merged; `workspace/workspace-saved-replies.md` and `workspace-composer-later.md` lose their "later" lines and this file is deleted.
