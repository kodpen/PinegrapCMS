---
name: workspace-look-leftover-screens
description: Plan workspace-look-leftover, step WL10: how the bulk Delete of the Data list's selection bar is put together (the button, the confirm, the result notice, the right per table)
priority: 2
---

## How is the bulk Delete put together? (WL10)
- Architect (2026-11-24, agent channel message 12602): it is the single Delete done for the chosen rows, nothing new in behaviour.
- Where: the Data list (`workspace_wtables.php`, `includes/workspace/wtable_list.php`), in the selection bar after Details: Delete as `pg-button pg-button-danger pg-button-sm`, from Design examples' selection bar.
- One confirm dialog (Design examples, Dialogs) that names the count and says the tables go to Deleted tables; its Delete is the danger button, Cancel ghost.
- Each chosen table goes to Deleted tables as the single Delete sends it, under the same right per table. A table the person may not delete is left; the result notice names the count deleted and each table left.
- A server action of its own with its unit test; texts through `lang()` and `php tools/tr_add.php`.
