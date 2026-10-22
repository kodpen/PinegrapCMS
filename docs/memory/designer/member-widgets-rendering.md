---
name: member-widgets-rendering
description: Membership widget render rules: closed feature = no output, canvas twin _sdMemberBindingOff, Messages node stamping, always render own tree, signed-in state.
---

## What happens to a disabled feature?
- Closed feature = no output. With `REMEMBER_ME` / `cfg.show_remember_me` off, the `remember_me` control drops WITH its label and emptied wrapper (`_pg_member_drop_controls`: `<label for>` matched by id; a `<div>` holding only the dropped one goes too). A link whose `href` is bound to an empty token drops (`_pg_member_drop_empty_links`). Empty section containers already drop via `pg_cf_apply_section_bindings`. No dead `href=""`, inert control or hidden `display:none`: absence.
- Canvas twin `_sdMemberBindingOff(node, cfg)` (style_designer.js) fades everything the renderer drops and says why with a badge (`data-sd-off-reason`: `_sdT('Off — site setting')` / `_sdT('Off — turn it on in the settings panel')`). Site flags come in `sdDesign.siteFlags` (`includes/designer_screen.php`: remember_me, forgot_password_link, password_hint, captcha, strong_password, google). A new drop rule in the renderer goes here too, lockstep with `_sdCatalogBindingDisabled`.
- The label follows its control via `for`; a wrapper whose every child drops fades too; a wrapper with one remaining child stays plain.

## How are errors and notices shown?
- From a Messages node with the handler's liveform name (`login`, `forgot_password`, `register` — NOT `registration`; `membership_entrance`). The node prints the message and consumes the form, so the renderer reads what it needs from the liveform (`email`, `screen`, `remember_me`) BEFORE adding the Messages node and calls `$lf->remove()` after the screen is drawn (a stale attempt must not shape the next visit). `$_SESSION['login_error']`-style keys never existed; do not reinvent.
- A message appears in its OWN widget's Messages node. The renderer stamps the widget's node `formName` with that handler's liveform name (`_pg_inject_messages_node($tree, '<name>')`); the node prints and consumes only that form; if none exists it is added at the start of the widget's first container.
- The page-level node (`<!--pg-messages-placeholder-->`, `get_page_content.php`) prints whatever is left AFTER widgets render. Order is fixed: `_expand_system_widgets` first, placeholder second.
- Every new widget renderer MUST stamp: an unstamped (empty `formName`) node prints EVERY form on the page and shows one widget's error in the other. A widget that submits nothing stamps a name nothing submits (`login_region`, `cart_link`): the account bar rendering before content once swallowed login and contact form errors (fixed 2026.4.6).

## Does the widget ever skip its own tree?
- Never. Signed-in visitor, confirmation screen, "closed feature" are all PRUNINGS of the tree (`_pg_member_render_signed_in`: controls, links, sections drop; the text goes to the liveform via `add_notice` and appears in the widget's Messages node inside the designer's container). A renderer returning a plain `<div class="alert">` instead prints the message container-less, glued to the page edge (done once; a user caught it). New state: tree stays, content changes.
- A signed-in visitor on the login widget sees the notice "You are already signed in." with Continue and Logout links, not the form; `$mode === 'edit'` shows the form so the page can be worked on. Dispatch (`_expand_system_widgets`) passes `$mode` to these two renderers.

Related: docs/memory/designer/member-widgets-handlers-and-forms.md, docs/memory/designer/system-widget-render-rules.md
