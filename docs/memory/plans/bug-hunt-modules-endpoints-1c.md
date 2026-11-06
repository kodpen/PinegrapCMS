---
name: bug-hunt-modules-endpoints-1c
description: Module list of plans/bug-hunt.md, part 3c: root endpoint groups mail, manifest and marketplace, one line each with its state
---

## Which root endpoint groups (mail to marketplace) are hunted?
- Done: mail, manifest #6100, marketplace
- Panel action table `includes/panel/actions.php` and `edit_user.php`, second round: done #7030 (five exempt handlers called validate_user() before a JSON session refusal; the toolbar switch answered a bare "Invalid login."); the other flags, the gate and edit_user.php (role gate on the posted id, role writes, last administrator, unlink/MFA/unlock/credit/delete, escaping) clean
