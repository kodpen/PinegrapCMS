---
name: workspace-user-email-change
description: A user's e-mail changed from the Workspace is refused when it is another account's e-mail or username
---

## What does the Workspace check before changing a user's e-mail?
`ws_change_check()` (`includes/workspace/changes.php`, case `user`, administrators only) refuses an empty address and one that another account holds as its `user_email` **or** its `user_username`, the account itself excluded. This is the same one namespace `pg_user_write_problem()` keeps on the panel: sign-in matches the identifier against both columns, so neither may belong to two accounts.

## What did it miss before?
Until the fix the check read only `user_email`. An administrator (or an applied proposal or scheduled action) could give user A the address `bob@example.com` while user B signs in with the username `bob@example.com`; that identifier then matched two accounts at sign-in and password reset.
