---
name: update-screens-finish
description: End of the Software Updater and Repair Software: the replace step writes the Site Log line, notice and flag, then the upgrade opens; why mode=autoupgrade / mode=done went
module: update
---

## What does the replace step write when it succeeds?
- The replace step writes the Site Log line ("Software updated successfully" or "Software repaired with the {var:1} package from the update server."), the notice shown after the upgrade and `software_update_available = 0`; a repair also drops the System Status and integrity caches (`pg_vendor_update_finish()`, `includes/fn/update.php`).
- Then the screen goes to the upgrade by a relative address: `install/index.php?automated_upgrade=true` for an administrator (Repair is theirs only). On the updater a designer or manager gets plain `install/index.php`: the automated address answers them a bare 403 and counts a failed unlock, the plain one shows the lock screen an administrator unlocks.
- Why: once the files are in place the code is ahead of `config.version`, so `validate_user()` (`pg_require_current_schema()`) sent the old GET completion addresses (`software_update.php?mode=autoupgrade`, `software_repair.php?mode=done`) to the installer before their body ran. No Site Log line, no notice, the caches kept. They are gone, and with them a link that could be forged.
- An update from an older release runs that release's replace step and script: its completion address reaches the installer while the code is ahead. A same-build repair made by such code, which does not rerun, lands back on Repair Software without the Site Log line.

## Tests?
- `tests/software_update_redirect_token_test.php`: the two addresses are gone, both scripts go to the upgrade, the updater's address by role, the replace step finishes after the token, the build record and its refusal; `tests/software_repair_test.php`.

Related: docs/memory/ui/update-screens-steps.md, docs/memory/release/rerun-upgrade-steps.md
