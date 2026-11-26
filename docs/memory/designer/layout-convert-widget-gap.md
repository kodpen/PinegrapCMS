---
name: layout-convert-widget-gap
description: What the layout converter does with a piece a page type's layout has and its widget lacks (LE10b to LE10d): a state, a carried setting, a page link, a second form or list; the guest button
---

## What is the rule?
- Architect (2026-11-26, agent channel request 3000), for the account pages and search results of plan legacy-editable, by the kind of piece:
- A state the widget can draw with a flag, a section or a token, through the same handler, with nothing an operator must choose: the step adds it to the widget, editor and server twins, as LE10a (#11710) did. Not asked.
- A setting the page type has and the widget holds: the seed and the converter carry it.
- A link to a page the type row names: a plain link bound to that page, kept only when the row names one. No widget option.
- A second form or a second list: never inside the widget (one widget, one form, one handler; `no-form-in-form.md`). It is a dropped piece: in a run the page stays a System region, the Region panel names the piece and the widget that draws it, "Convert to design elements" converts after the confirm.
- A new option an operator must choose is the owner's and goes through the architect.

## Why no result that carries several widgets?
- A layout with a login form beside the sign-up form could become two widgets inside the theme's wrappers. The converter answers one widget's tree; a fragment of several would be new machinery in the run, the editor's Convert and the Region panel, for one page a site. The designer adds the Login widget beside the converted one by hand.

## What happens to "Continue as a Guest"?
- It is a state: the `registration` widget gets a section `guest`, drawn only when the request carries `allow_guest=true`, with one button bound to its own action. The widget's starter has it too.
- The server prints a small form of its own after the widget's form (`registration_entrance.php`, the token, `continue=true`, `send_to`, `allow_guest`) and gives the button that form's id in its `form` attribute. Nothing is nested, and Enter in a sign-up field cannot press "Continue": inside the sign-up form it would be the default button wherever a theme puts it first.
- The widget's own form carries the hidden `allow_guest` then, as the classic forms do, so a refused sign-up keeps the guest's way.
- Why: only that form sets `$_SESSION['software']['guest']`, which `get_page.php`, `get_file.php` and `authentication.php` read. A stock entrance page the run moved before this gave a guest no way on.

## What of the three steps follows?
- LE10b: the Register form becomes the `registration` / `membership` widget's tree. The login form of both layouts is a dropped piece whose reason names the Login widget; the widget's `login_link` section stays the way to the login page.
- LE10c: the address type switch is carried into `cfg.address_type`. "What is this?" is a link bound to the page the type row names, dropped when it names none.
- LE10d: one list, the widget's. The Featured and Catalog loops merge into it (`_pg_lc_merge_loops()`) where a merged row loses nothing, each kept under the row's kind (`__result_type`, `__result_is_featured`). A row flag for the first row of its kind keeps the three headings only if the widget draws the results in that order; otherwise they are dropped pieces.

Related: docs/memory/designer/layout-type-overview.md, docs/memory/designer/member-widgets-handlers-and-forms.md, docs/memory/designer/no-form-in-form.md
