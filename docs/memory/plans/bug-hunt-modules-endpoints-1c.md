---
name: bug-hunt-modules-endpoints-1c
description: Module list of plans/bug-hunt.md, part 3c: root endpoint groups mail, manifest and marketplace, one line each with its state
---

## Which root endpoint groups (mail to marketplace) are hunted?
- Done: mail, manifest #6100, marketplace
- Panel action table `includes/panel/actions.php` and `edit_user.php`, second round: taken by worker of Arzu V5 (exempt handlers that redirect an ended session instead of a JSON refusal)
