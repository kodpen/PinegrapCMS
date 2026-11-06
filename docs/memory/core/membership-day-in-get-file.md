---
name: membership-day-in-get-file
description: Which calendar day decides a running membership or private access in get_file.php (site time zone, set before USER_MEMBER); the hours an ended membership still opened files
---

## Which day ends a membership?
- The site's day: a membership (contacts.expiration_date) or a private access (aclfolder.expiration_date) runs through its date and ends when the site's `date('Y-m-d')` is past it. Pages get the site zone from init.php before initialize_user().

## Why does get_file.php set the zone itself?
- get_file.php never runs init.php; its own initialize_timezone() sets the site zone (TIMEZONE, else the server's). Every date check there calls it first: the membership branch, check_private_access(), and initialize_user() before it defines USER_MEMBER.

## What went wrong before?
- get_file.php's initialize_user() decided USER_MEMBER on the server's zone. check_view_access() reads USER_MEMBER for comment attachments, form list/item view files and record files in membership folders, so on a site ahead of the server (Istanbul on a UTC server) a membership that ended yesterday still opened them until the server's day turned. Fixed by calling initialize_timezone() before the check; guarded by tests/get_file_member_timezone_test.php.
