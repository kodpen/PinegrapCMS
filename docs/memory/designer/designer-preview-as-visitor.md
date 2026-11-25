---
name: designer-preview-as-visitor
description: How a widget of the Preview is drawn for the chosen visitor (PW2c, architect 2026-11-26): one viewer reader in place of the USER_ constants, the sample account with no row, what is never read
module: designer_preview_render
---

## What was the question?
- Renderers read the `USER_LOGGED_IN` family of constants. A constant is set once a request, and a Preview is an `api.php` call that sets them for the operator. The sample account (`canvas-sample-account.md`) has no user row and gets none: the Preview writes nothing.

## What is the answer?
- Architect (2026-11-26, agent channel request 2993): no constant is redefined and no row is made. One reader, `pg_preview_as_viewer()` in `designer_preview_render.php`, says who a widget is drawn for.
- Outside a Preview widget (`pg_preview_as_current()` empty) it gives the request's own constants back, read with `defined()`, so a live visit draws as before. This is the rule of the PW2b readers.
- Inside one it gives what the setup's visitor says:
  - `signed_out`: not signed in, id 0, no role, no member.
  - `signed_in`: signed in, id 0, a plain user's role, the sample account's name, user name and e-mail, the same values the canvas draws.
  - `member`: as `signed_in`, a member whom the page's access rules let in.
  - `first_visit`, `returning`: signed out; the audience conditions' reader of the visit (`audience-conditions.md`) gets the chosen one in place of the `pg_ret` cookie.
- A renderer that already asks whether the render is a background one to draw as a signed-out visitor (the comments widget's `pg_comments_widget_audience()`) asks the viewer reader, and "Signed out" stays as it draws today.

## Which renderers change?
- The step's first commit is a census in its PR body: every renderer `preview_widgets` reaches that reads the `USER_` family, in three classes.
  - It draws differently signed in or out (login, log out, my account, a menu's account links, comments, member-only content): it reads the viewer reader.
  - It reads rows by `USER_ID` (orders, address book, profile, invoices, e-mail preferences): for the sample account it draws sample rows, the canvas's values kept in one PHP list beside the reader. It never runs its statement with id 0, with the operator's id or with a real customer's.
  - It checks an operator's right (an edit link, a role): untouched; the operator stays the operator.
- A renderer the census does not reach keeps its constants: the step changes nothing a Preview does not draw.

## What must the tests hold?
- Outside the scope the reader equals the constants (a live visit is unchanged) for each changed renderer.
- Inside it the operator's id reaches no statement: a wiring test lists the census's files of the first two classes and fails for a bare `USER_ID` or `USER_LOGGED_IN` left in them.
- The never-writes assertions of `designer-preview-as-server.md` hold for each visitor.

## How is it cut?
- If the census is more than one M step: PW2c takes signed in, signed out and member; PW2d takes first visit and returning. PW4 then waits for both.
- A chosen record with a real customer's address, e-mail or phone is masked in a signed-in view, as the plan says; the mask is one function beside the reader.

Related: docs/memory/designer/designer-preview-as-readers.md, docs/memory/designer/designer-preview-as-server.md, docs/memory/designer/canvas-sample-account.md
