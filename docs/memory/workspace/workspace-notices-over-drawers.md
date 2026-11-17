---
name: workspace-notices-over-drawers
description: Workspace notices (toast(), upload cards) stand inside the open drawer or dialog through pgUI.hostLayer(); New e-mail marks the field a refusal names (#10081)
---

## Where does a Workspace notice stand while a drawer is open?

Inside that drawer. `toastHost()` in `assets/js/workspace.js` makes the `toast-container` once and hands it to `pgUI.hostLayer()` on every `toast()` and upload card: a modal `<dialog>` and its backdrop are in the top layer, so a container left on the body was drawn dimmed under the backdrop (#10081). As the drawer starts hiding the container goes back to the body.

## Which field does a refused New e-mail mark?

The panel's `ws_mail_new` answers `field` from `ws_mail_send_error_field()` (`includes/workspace/mail_send.php`): the To, Cc or Bcc field holding the bad or refused address (`bad_address`, `blocked`; To when not found), To for `no_to` / `too_many`, `subject`, `text`; '' otherwise. The drawer marks it with `is-invalid` and `aria-invalid`, focuses it and opens Cc/Bcc when needed; typing clears the mark. `unknown_domain` keeps its own warning in the foot.
